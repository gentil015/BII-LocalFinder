"""
Admin ranking score helper for providers.

This module implements the same manual score rules as the PHP helper in
includes/admin_ranking.php.
"""

from typing import Any, Dict, Iterable


def clamp(value: int, minimum: int = 0, maximum: int = 100) -> int:
    return max(minimum, min(maximum, value))


def calculate_admin_score(provider: Dict[str, Any]) -> int:
    """Calculate admin ranking score for one provider.

    Args:
        provider: Provider data as a dict-like object.

    Returns:
        An integer score between 0 and 100.
    """
    override = provider.get('admin_score_override')
    if override is not None:
        try:
            return clamp(int(override), 0, 100)
        except (TypeError, ValueError):
            pass

    score = 0

    if bool(provider.get('is_featured')):
        score += 50

    is_verified = bool(provider.get('is_verified'))
    if not is_verified:
        verification_level = str(provider.get('verification_level', '')).lower()
        is_verified = verification_level in {'verified', 'gold', 'premium'}

    if is_verified:
        score += 30

    promotion_boost = clamp(int(provider.get('admin_promotion_boost', provider.get('promotion_boost', 0))), 0, 20)
    score += promotion_boost

    priority_level = clamp(int(provider.get('admin_priority_level', provider.get('priority_level', 0))), 0, 3)
    score += priority_level * 10

    return clamp(score, 0, 100)


def update_admin_scores_for_all_providers(conn) -> Dict[int, int]:
    """Compute scores for all providers and optionally persist them.

    Args:
        conn: DB-API connection with a cursor() method.

    Returns:
        Mapping of provider_id to computed score.
    """
    cursor = conn.cursor()
    cursor.execute(
        "SELECT id, is_featured, is_verified, verification_level, admin_promotion_boost, admin_priority_level, admin_score_override FROM service_providers"
    )
    providers = cursor.fetchall()
    columns = [col[0] for col in cursor.description]

    results = {}
    for row in providers:
        provider = dict(zip(columns, row))
        score = calculate_admin_score(provider)
        results[int(provider['id'])] = score

    return results


if __name__ == '__main__':
    sample = {
        'is_featured': 1,
        'is_verified': 1,
        'admin_promotion_boost': 15,
        'admin_priority_level': 2,
    }
    print('Admin score:', calculate_admin_score(sample))  # 100
