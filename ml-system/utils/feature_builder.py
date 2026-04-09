"""
feature_builder.py
------------------
Builds ML feature vectors for providers directly from the live database.
Used for real-time prediction without relying on the CSV file.

Usage:
    from utils.feature_builder import FeatureBuilder

    fb = FeatureBuilder()
    features = fb.for_provider(provider_id=42)
    # returns: {"views":..., "clicks":..., "messages":...,
    #            "rating":..., "price":..., "avg_response_time":...}

    all_features = fb.for_all_active_providers()
    # returns: [{"provider_id":1, "views":..., ...}, ...]
"""

from utils.db_connection import get_connection


# Feature column names — must match model training order exactly
FEATURE_COLS = [
    "views",
    "clicks",
    "messages",
    "rating",
    "price",
    "avg_response_time",
    "user_avg_price",
    "user_avg_response_time",
    "user_total_bookings",
]


class FeatureBuilder:
    """Fetches and assembles feature vectors from the live database."""

    def __init__(self):
        self._conn = None

    def _connect(self):
        if self._conn is None or not self._conn.is_connected():
            self._conn = get_connection()

    def close(self):
        if self._conn and self._conn.is_connected():
            self._conn.close()

    # ── Single provider ──────────────────────────────────────────────────────
    def for_provider(self, provider_id: int) -> dict:
        """
        Returns a feature dict for one provider.
        All missing values default to safe zeros / averages.
        """
        self._connect()
        cursor = self._conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT
                /* profile views */
                COALESCE((
                    SELECT COUNT(*) FROM provider_views pv
                    WHERE pv.provider_id = sp.id
                ), 0) AS views,

                /* click events */
                COALESCE((
                    SELECT COUNT(*) FROM click_logs cl
                    WHERE cl.target_type = 'provider' AND cl.target_id = sp.id
                ), 0) AS clicks,

                /* messages received by this provider's user account */
                COALESCE((
                    SELECT COUNT(*) FROM messages m
                    WHERE m.receiver_id = sp.user_id
                ), 0) AS messages,

                /* star rating */
                COALESCE(sp.average_rating, 0.0) AS rating,

                /* average service price */
                COALESCE((
                    SELECT AVG(ps.price)
                    FROM provider_services ps
                    WHERE ps.provider_id = sp.id AND ps.is_available = 1
                ), 0.0) AS price,

                /* average hours from booking creation to first response */
                COALESCE((
                    SELECT AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.responded_at))
                    FROM bookings b
                    WHERE b.provider_id = sp.id AND b.responded_at IS NOT NULL
                ), 24.0) AS avg_response_time

            FROM service_providers sp
            WHERE sp.id = %s
        """, (provider_id,))

        row = cursor.fetchone()
        cursor.close()

        if not row:
            # Return neutral defaults so ranking still works
            return {col: 0.0 for col in FEATURE_COLS}

        return {col: float(row[col] or 0) for col in FEATURE_COLS}

    # ── Single provider with user context ──────────────────────────────────────
    def for_provider_with_user(self, provider_id: int, user_id: int) -> dict:
        """
        Returns a feature dict for one provider including user behavior features.
        All missing values default to safe zeros / averages.
        """
        self._connect()
        cursor = self._conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT
                /* provider profile features */
                COALESCE((SELECT COUNT(*) FROM provider_views pv WHERE pv.provider_id = sp.id), 0) AS views,
                COALESCE((SELECT COUNT(*) FROM click_logs cl WHERE cl.target_type = 'provider' AND cl.target_id = sp.id), 0) AS clicks,
                COALESCE((SELECT COUNT(*) FROM messages m WHERE m.receiver_id = sp.user_id), 0) AS messages,
                COALESCE(sp.average_rating, 0.0) AS rating,
                COALESCE((SELECT AVG(ps.price) FROM provider_services ps WHERE ps.provider_id = sp.id AND ps.is_available = 1), 0.0) AS price,
                COALESCE((SELECT AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.responded_at)) FROM bookings b WHERE b.provider_id = sp.id AND b.responded_at IS NOT NULL), 24.0) AS avg_response_time,

                /* user behavior features */
                COALESCE(
                    up.user_avg_price,
                    (SELECT AVG(amount) FROM bookings b3 WHERE b3.client_id = %s AND b3.amount IS NOT NULL),
                    0.0
                ) AS user_avg_price,
                COALESCE(
                    up.user_avg_response_time,
                    (SELECT AVG(TIMESTAMPDIFF(HOUR, b3.created_at, b3.responded_at))
                     FROM bookings b3
                     WHERE b3.client_id = %s AND b3.responded_at IS NOT NULL),
                    24.0
                ) AS user_avg_response_time,
                COALESCE(
                    up.user_total_bookings,
                    (SELECT COUNT(*) FROM bookings b3 WHERE b3.client_id = %s),
                    0
                ) AS user_total_bookings

            FROM service_providers sp
            LEFT JOIN user_profiles up ON up.user_id = %s
            WHERE sp.id = %s
        """, (user_id, user_id, user_id, user_id, provider_id))

        row = cursor.fetchone()
        cursor.close()

        if not row:
            # Return neutral defaults so ranking still works
            return {col: 0.0 for col in FEATURE_COLS}

        return {col: float(row[col] or 0) for col in FEATURE_COLS}

    # ── All active providers ─────────────────────────────────────────────────
    def for_all_active_providers(self) -> list:
        """
        Returns a list of dicts, each containing provider_id + feature values.
        Only active, non-banned providers are included.
        """
        self._connect()
        cursor = self._conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT
                sp.id AS provider_id,

                COALESCE((
                    SELECT COUNT(*) FROM provider_views pv
                    WHERE pv.provider_id = sp.id
                ), 0) AS views,

                COALESCE((
                    SELECT COUNT(*) FROM click_logs cl
                    WHERE cl.target_type = 'provider' AND cl.target_id = sp.id
                ), 0) AS clicks,

                COALESCE((
                    SELECT COUNT(*) FROM messages m
                    WHERE m.receiver_id = sp.user_id
                ), 0) AS messages,

                COALESCE(sp.average_rating, 0.0) AS rating,

                COALESCE((
                    SELECT AVG(ps.price)
                    FROM provider_services ps
                    WHERE ps.provider_id = sp.id AND ps.is_available = 1
                ), 0.0) AS price,

                COALESCE((
                    SELECT AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.responded_at))
                    FROM bookings b
                    WHERE b.provider_id = sp.id AND b.responded_at IS NOT NULL
                ), 24.0) AS avg_response_time

            FROM service_providers sp
            WHERE sp.is_active = 1 AND sp.is_banned = 0
        """)

        rows = cursor.fetchall()
        cursor.close()

        result = []
        for row in rows:
            entry = {"provider_id": int(row["provider_id"])}
            for col in FEATURE_COLS:
                entry[col] = float(row[col] or 0)
            result.append(entry)

        return result

    # ── All active providers with user context ───────────────────────────────
    def for_all_active_providers_with_user(self, user_id: int) -> list:
        """
        Returns a list of dicts, each containing provider_id + feature values including user behavior.
        Only active, non-banned providers are included.
        User features are the same for all providers (from the user's profile).
        """
        self._connect()
        cursor = self._conn.cursor(dictionary=True)

        cursor.execute("""
            SELECT
                sp.id AS provider_id,

                /* provider profile features */
                COALESCE((SELECT COUNT(*) FROM provider_views pv WHERE pv.provider_id = sp.id), 0) AS views,
                COALESCE((SELECT COUNT(*) FROM click_logs cl WHERE cl.target_type = 'provider' AND cl.target_id = sp.id), 0) AS clicks,
                COALESCE((SELECT COUNT(*) FROM messages m WHERE m.receiver_id = sp.user_id), 0) AS messages,
                COALESCE(sp.average_rating, 0.0) AS rating,
                COALESCE((SELECT AVG(ps.price) FROM provider_services ps WHERE ps.provider_id = sp.id AND ps.is_available = 1), 0.0) AS price,
                COALESCE((SELECT AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.responded_at)) FROM bookings b WHERE b.provider_id = sp.id AND b.responded_at IS NOT NULL), 24.0) AS avg_response_time,

                /* user behavior features (same for all providers) */
                COALESCE(
                    up.user_avg_price,
                    (SELECT AVG(amount) FROM bookings b3 WHERE b3.client_id = %s AND b3.amount IS NOT NULL),
                    0.0
                ) AS user_avg_price,
                COALESCE(
                    up.user_avg_response_time,
                    (SELECT AVG(TIMESTAMPDIFF(HOUR, b3.created_at, b3.responded_at))
                     FROM bookings b3
                     WHERE b3.client_id = %s AND b3.responded_at IS NOT NULL),
                    24.0
                ) AS user_avg_response_time,
                COALESCE(
                    up.user_total_bookings,
                    (SELECT COUNT(*) FROM bookings b3 WHERE b3.client_id = %s),
                    0
                ) AS user_total_bookings

            FROM service_providers sp
            LEFT JOIN user_profiles up ON up.user_id = %s
            WHERE sp.is_active = 1 AND sp.is_banned = 0
        """, (user_id, user_id, user_id, user_id))

        rows = cursor.fetchall()
        cursor.close()

        result = []
        for row in rows:
            entry = {"provider_id": int(row["provider_id"])}
            for col in FEATURE_COLS:
                entry[col] = float(row[col] or 0)
            result.append(entry)

        return result

    # ── Validate feature dict ────────────────────────────────────────────────
    @staticmethod
    def validate(features: dict) -> dict:
        """
        Ensures all required keys are present and values are non-negative floats.
        Fills missing keys with 0.0.
        """
        clean = {}
        for col in FEATURE_COLS:
            val = features.get(col, 0.0)
            try:
                val = float(val)
            except (TypeError, ValueError):
                val = 0.0
            clean[col] = max(0.0, val)
        return clean

    # ── Context manager support ──────────────────────────────────────────────
    def __enter__(self):
        return self

    def __exit__(self, *_):
        self.close()