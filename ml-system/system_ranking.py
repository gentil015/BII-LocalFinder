"""
System Ranking Score Helper

This module calculates an automatic provider score based on performance and activity.
"""

from typing import Any, Dict, Iterable


def clamp(value: int, minimum: int = 0, maximum: int = 100) -> int:
    return max(minimum, min(maximum, value))


def get_value(provider: Dict[str, Any], key: str, default: Any = None) -> Any:
    return provider.get(key, default)


def calculate_system_score(provider: Dict[str, Any]) -> int:
    """Calculate a system ranking score from provider metrics.

    Rules:
      1. Availability: online adds +20
      2. Response time: faster means more points
      3. Completion rate: 0-1 contributes up to +50
      4. Rating: 1-5 normalized to 0-20
      5. Recent activity: active in last 24h adds +10
    """
    score = 0

    # 1. Availability boost for online providers.
    if bool(get_value(provider, 'is_online', False)):
        score += 20

    # 2. Response time: use minutes for the formula.
    response_minutes = get_value(provider, 'avg_response_time_minutes', None)
    if response_minutes is None:
        response_minutes = get_value(provider, 'response_time_in_minutes', None)
    if response_minutes is None:
        response_minutes = get_value(provider, 'avg_response_time', 0)
    try:
        response_minutes = max(0, int(response_minutes))
    except (TypeError, ValueError):
        response_minutes = 0
    score += max(0, 30 - response_minutes)

    # 3. Completion rate contribution (0-1 expected).
    completion_rate = float(get_value(provider, 'completion_rate', 0.0))
    if completion_rate > 1.0:
        completion_rate = min(1.0, completion_rate / 100.0)
    score += completion_rate * 50

    # 4. Normalize average rating 1-5 to 0-20.
    rating = float(get_value(provider, 'average_rating', 0.0))
    rating = max(0.0, min(5.0, rating))
    score += (rating / 5.0) * 20

    # 5. Recent activity bonus for last 24h.
    last_active = get_value(provider, 'last_active', get_value(provider, 'updated_at', None))
    if last_active is not None:
        from datetime import datetime, timedelta
        try:
            last_active_dt = datetime.fromisoformat(str(last_active))
            if last_active_dt >= datetime.now() - timedelta(hours=24):
                score += 10
        except ValueError:
            pass

    return clamp(int(round(score)))


def update_system_scores_for_all_providers(conn) -> Dict[int, int]:
    """Compute system scores for all providers from the DB.

    Args:
        conn: DB-API connection object.

    Returns:
        Mapping of provider_id to computed score.
    """
    cursor = conn.cursor()
    cursor.execute(
        "SELECT id, is_online, avg_response_time_minutes, response_time_in_minutes, avg_response_time, completion_rate, average_rating, last_active, updated_at FROM service_providers"
    )
    rows = cursor.fetchall()
    columns = [col[0] for col in cursor.description]

    results = {}
    for row in rows:
        provider = dict(zip(columns, row))
        score = calculate_system_score(provider)
        results[int(provider['id'])] = score
    return results


if __name__ == '__main__':
    sample_provider = {
        'is_online': 1,
        'avg_response_time_minutes': 12,
        'completion_rate': 0.88,
        'average_rating': 4.6,
        'last_active': '2026-04-01T10:15:00',
    }
    print('System score:', calculate_system_score(sample_provider))
