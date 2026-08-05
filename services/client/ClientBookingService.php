<?php

require_once __DIR__ . '/../../repositories/client/ClientBookingRepository.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/chat.php';

class ClientBookingService
{
    private ClientBookingRepository $repository;

    public function __construct(?ClientBookingRepository $repository = null)
    {
        $this->repository = $repository ?? new ClientBookingRepository();
    }

    public function buildViewModel(PDO $db, int $providerId, int $serviceId = 0, ?int $shareId = null): array
    {
        $this->repository->ensureProviderShareIdColumn($db);

        $provider = $this->repository->getProviderById($db, $providerId);
        if ($provider === null) {
            return [
                'provider' => null,
                'services' => [],
                'auto_selected_service' => null,
                'schedule' => [],
                'working_days' => [1, 2, 3, 4, 5],
                'availability_exceptions' => [],
                'time_off_periods' => [],
                'fully_booked_dates' => [],
                'slot_duration' => 60,
                'buffer_minutes' => 0,
                'max_daily_bookings' => 0,
                'share_id' => $shareId,
            ];
        }

        $services = $this->repository->getServicesForProvider($db, $providerId);
        $autoSelectedService = null;
        if ($serviceId) {
            foreach ($services as $svc) {
                if ((int) $svc['id'] === $serviceId) {
                    $autoSelectedService = $svc;
                    break;
                }
            }
        }

        $schedule = $this->repository->getScheduleForProvider($db, $providerId);
        $workingDays = [];
        if (!empty($schedule['working_days'])) {
            $workingDays = array_map('intval', array_filter(array_map('trim', explode(',', $schedule['working_days']))));
        }
        if (empty($workingDays)) {
            $workingDays = [1, 2, 3, 4, 5];
        }

        $availabilityExceptions = $this->repository->getAvailabilityExceptions($db, $providerId);
        $timeOffPeriods = $this->repository->getTimeOffPeriods($db, $providerId);
        $slotDuration = !empty($schedule['slot_duration']) ? intval($schedule['slot_duration']) : 60;
        $bufferMinutes = !empty($schedule['buffer_time']) ? intval($schedule['buffer_time']) : 0;
        $maxDailyBookings = !empty($schedule['max_daily_bookings']) ? intval($schedule['max_daily_bookings']) : 0;
        $fullyBookedDates = $this->repository->getFullyBookedDates(
            $db,
            $providerId,
            $slotDuration,
            $bufferMinutes,
            $maxDailyBookings,
            $schedule['working_hours_start'] ?? null,
            $schedule['working_hours_end'] ?? null,
            $schedule['break_start'] ?? null,
            $schedule['break_end'] ?? null
        );

        return [
            'provider' => $provider,
            'services' => $services,
            'auto_selected_service' => $autoSelectedService,
            'schedule' => $schedule,
            'working_days' => $workingDays,
            'availability_exceptions' => $availabilityExceptions,
            'time_off_periods' => $timeOffPeriods,
            'fully_booked_dates' => $fullyBookedDates,
            'slot_duration' => $slotDuration,
            'buffer_minutes' => $bufferMinutes,
            'max_daily_bookings' => $maxDailyBookings,
            'share_id' => $shareId,
        ];
    }

    public function submitBooking(PDO $db, int $providerId, int $serviceId, ?int $shareId, array $post, array $session, array $provider, array $services, array $schedule, array $workingDays, array $timeOffPeriods, array $availabilityExceptions): array
    {
        $bookingErrors = [];
        $bookingSuccess = '';
        $bookingRef = '';

        $serviceId = intval($post['service_id'] ?? 0);
        $serviceDesc = trim((string) ($post['serviceDesc'] ?? ''));
        $preferredDate = trim((string) ($post['preferred_date'] ?? ''));
        $preferredTime = trim((string) ($post['preferred_time'] ?? ''));
        $clientName = trim((string) ($post['client_name'] ?? ''));
        $clientPhone = trim((string) ($post['client_phone'] ?? ''));
        $clientLocation = trim((string) ($post['client_location'] ?? ''));
        $urgencyLevel = trim((string) ($post['urgency_level'] ?? 'normal'));
        $clientProposedPrice = !empty($post['client_proposed_price']) ? floatval($post['client_proposed_price']) : null;

        if (empty($session['user_id'])) {
            $bookingErrors[] = 'Please log in to submit a booking.';
        }

        if (empty($serviceDesc) || empty($preferredDate) || !$serviceId) {
            $bookingErrors[] = 'Please fill all required fields';
        }
        if (empty($clientName) || empty($clientPhone) || empty($clientLocation)) {
            $bookingErrors[] = 'Please enter your name, phone number, and location.';
        }

        $selectedDate = null;
        $dayOfWeek = null;
        if ($preferredDate) {
            $selectedDate = new DateTime($preferredDate);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            if ($selectedDate < $today) {
                $bookingErrors[] = 'Please select a date in the future';
            }

            $dayOfWeek = (int) $selectedDate->format('N');
            if (!in_array($dayOfWeek, $workingDays, true)) {
                $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $bookingErrors[] = 'Provider is not available on ' . $dayNames[$dayOfWeek - 1] . 's';
            }

            foreach ($timeOffPeriods as $timeOff) {
                $timeOffStart = new DateTime($timeOff['start_date']);
                $timeOffEnd = new DateTime($timeOff['end_date']);
                if ($selectedDate >= $timeOffStart && $selectedDate <= $timeOffEnd) {
                    $bookingErrors[] = 'Provider is on time off from ' . date('M d', strtotime($timeOff['start_date'])) . ' to ' . date('M d, Y', strtotime($timeOff['end_date']));
                    break;
                }
            }

            foreach ($availabilityExceptions as $ex) {
                if (($ex['date'] ?? '') == $preferredDate && ($ex['is_available'] ?? 1) == 0) {
                    $bookingErrors[] = 'Provider is not available on this date';
                    break;
                }
            }
        }

        if ($preferredTime && ($schedule['working_hours_start'] ?? null) && ($schedule['working_hours_end'] ?? null)) {
            $time = strtotime($preferredTime);
            $startTime = strtotime($schedule['working_hours_start']);
            $endTime = strtotime($schedule['working_hours_end']);
            if ($time < $startTime || $time > $endTime) {
                $bookingErrors[] = 'Please select a time between ' . date('g:i A', $startTime) . ' and ' . date('g:i A', $endTime);
            }
        }

        $selectedService = null;
        foreach ($services as $svc) {
            if ((int) $svc['id'] === $serviceId) {
                $selectedService = $svc;
                break;
            }
        }

        if (empty($bookingErrors)) {
            if (!$selectedService || !$selectedService['is_available']) {
                $bookingErrors[] = 'Invalid service selected';
            }
        }

        if (empty($bookingErrors) && $selectedService) {
            $serviceAvailabilityDays = [];
            if (!empty($selectedService['availability_days'])) {
                $serviceAvailabilityDays = array_map('intval', array_filter(array_map('trim', explode(',', $selectedService['availability_days']))));
            }
            if (empty($serviceAvailabilityDays)) {
                $serviceAvailabilityDays = $workingDays;
            }

            if ($preferredDate && !in_array($dayOfWeek, $serviceAvailabilityDays, true)) {
                $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $bookingErrors[] = 'Selected service is not available on ' . $dayNames[$dayOfWeek - 1] . 's';
            }

            if ($preferredTime && !empty($selectedService['time_slots'])) {
                $availableServiceSlots = [];
                $rawSlots = $selectedService['time_slots'];
                $decoded = json_decode($rawSlots, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $slot) {
                        if (is_string($slot)) {
                            $availableServiceSlots[] = trim($slot);
                        } elseif (is_array($slot) && isset($slot['start'], $slot['end'])) {
                            $availableServiceSlots[] = trim($slot['start']) . '-' . trim($slot['end']);
                        }
                    }
                } else {
                    foreach (preg_split('/[\r\n]+/', $rawSlots) as $line) {
                        $line = trim((string) $line);
                        if ($line !== '') {
                            $availableServiceSlots[] = $line;
                        }
                    }
                }

                if (!empty($availableServiceSlots)) {
                    $validTime = false;
                    $serviceDuration = intval($selectedService['duration']) ?: 60;
                    foreach ($availableServiceSlots as $slotRange) {
                        $parts = array_map('trim', explode('-', $slotRange));
                        if (count($parts) !== 2) {
                            continue;
                        }
                        $slotStart = strtotime($parts[0]);
                        $slotEnd = strtotime($parts[1]);
                        $selectedTime = strtotime($preferredTime);
                        if ($slotStart !== false && $slotEnd !== false && $selectedTime !== false) {
                            if ($selectedTime >= $slotStart && ($selectedTime + ($serviceDuration * 60)) <= $slotEnd) {
                                $validTime = true;
                                break;
                            }
                        }
                    }
                    if (!$validTime) {
                        $bookingErrors[] = 'Selected time is outside the service\'s available time slots.';
                    }
                }
            }
        }

        if (empty($bookingErrors)) {
            $bookingStatus = 'pending';
            if ($selectedService && $selectedService['booking_mode'] === 'instant' && empty($selectedService['negotiable'])) {
                $bookingStatus = 'confirmed';
            }

            $bookingAmount = $clientProposedPrice !== null
                ? $clientProposedPrice
                : (isset($selectedService['price']) ? floatval($selectedService['price']) : null);

            try {
                $bookingId = $this->repository->insertBooking($db, [
                    'client_id' => (int) $session['user_id'],
                    'provider_id' => $providerId,
                    'service_id' => $serviceId,
                    'service_description' => $serviceDesc,
                    'preferred_date' => $preferredDate,
                    'preferred_time' => $preferredTime,
                    'location' => $clientLocation,
                    'amount' => $bookingAmount,
                    'status' => $bookingStatus,
                    'provider_share_id' => $shareId ? $shareId : null,
                ]);

                $bookingRef = '#BK-' . date('Y') . '-' . str_pad((string) $bookingId, 5, '0', STR_PAD_LEFT);

                if ($clientProposedPrice > 0) {
                    require_once __DIR__ . '/../../payments/PaymentManager.php';
                    $paymentManager = new PaymentManager();
                    $paymentId = $paymentManager->createPaymentForBooking($bookingId);
                    if ($paymentId) {
                        $stmt = $db->prepare("INSERT INTO user_activities (user_id, activity_type, description, ip_address, user_agent) VALUES (?, 'payment_created', ?, ?, ?)");
                        $stmt->execute([
                            $session['user_id'],
                            "Payment created for booking {$bookingRef}",
                            $_SERVER['REMOTE_ADDR'] ?? null,
                            $_SERVER['HTTP_USER_AGENT'] ?? null,
                        ]);
                    }
                }

                if (!empty($provider['email'])) {
                    require_once __DIR__ . '/../../includes/mailer.php';
                    Mailer::sendBookingNotification(
                        $provider['email'],
                        $provider['full_name'],
                        $session['user_name'] ?? '',
                        $serviceDesc
                    );
                }

                try {
                    require_once __DIR__ . '/../../includes/notifications.php';
                    notifyNewBooking($providerId, $bookingId, [
                        'client_name' => $session['user_name'] ?? '',
                        'service_description' => $serviceDesc,
                    ]);
                } catch (Exception $e) {
                    error_log('Booking notification error: ' . $e->getMessage());
                }

                $providerUserId = $provider['user_id'] ?? $providerId;
                sendMessage((int) $session['user_id'], (int) $providerUserId, 'New booking created: ' . $bookingRef);

                $bookingSuccess = 'Booking request sent successfully! The provider will contact you soon.';
                return [
                    'booking_errors' => $bookingErrors,
                    'booking_success' => $bookingSuccess,
                    'booking_ref' => $bookingRef,
                    'redirect' => 'messages.php?with=' . $providerUserId . '&booking_id=' . $bookingId,
                ];
            } catch (Exception $e) {
                $bookingErrors[] = 'Failed to save booking. Please try again.';
                error_log('Booking save error: ' . $e->getMessage());
            }
        }

        return [
            'booking_errors' => $bookingErrors,
            'booking_success' => $bookingSuccess,
            'booking_ref' => $bookingRef,
            'redirect' => null,
        ];
    }
}
