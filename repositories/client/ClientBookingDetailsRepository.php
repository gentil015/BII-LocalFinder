<?php

class ClientBookingDetailsRepository
{
    public function getBookingByIdForClient(PDO $db, int $bookingId, int $userId): ?array
    {
        $stmt = $db->prepare("
            SELECT 
                b.*,
                u.full_name as provider_name,
                u.phone as provider_phone,
                u.email as provider_email,
                u.profile_image as provider_image,
                sp.profession,
                sp.average_rating,
                sp.total_reviews,
                sp.is_verified,
                ps.name as service_name,
                ps.booking_mode,
                ps.description as service_full_desc,
                cat.name as category_name
            FROM bookings b
            LEFT JOIN users u ON b.provider_id = u.id
            LEFT JOIN service_providers sp ON b.provider_id = sp.user_id
            LEFT JOIN provider_services ps ON b.service_id = ps.id
            LEFT JOIN categories cat ON ps.category_id = cat.id
            WHERE b.id = ? AND b.client_id = ?
        ");
        $stmt->execute([$bookingId, $userId]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        return $booking === false ? null : $booking;
    }
}
