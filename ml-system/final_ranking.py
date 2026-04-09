"""
Final Ranking Engine

Combines ML, system, and admin scores into a single final score.
"""

from __future__ import annotations

from typing import Any, Dict, Iterable, List, Optional, Tuple


def normalize_score(value: Any) -> float:
    """Normalize a raw score into the 0-100 range."""
    if value is None:
        return 0.0

    try:
        score = float(value)
    except (TypeError, ValueError):
        return 0.0

    if 0.0 <= score <= 1.0:
        score *= 100.0

    return max(0.0, min(100.0, score))


def normalize_weights(weights: Optional[Dict[str, float]] = None) -> Dict[str, float]:
    """Normalize weights so they sum to 1."""
    defaults = {'ml': 0.5, 'system': 0.3, 'admin': 0.2}
    if not weights:
        return defaults

    normalized = {
        'ml': float(weights.get('ml', defaults['ml'])),
        'system': float(weights.get('system', defaults['system'])),
        'admin': float(weights.get('admin', defaults['admin'])),
    }
    total = sum(normalized.values())
    if total <= 0.0:
        return defaults

    return {k: v / total for k, v in normalized.items()}


def get_first_available(provider: Dict[str, Any], keys: Iterable[str], default: Any = 0) -> Any:
    for key in keys:
        if key in provider and provider[key] is not None:
            return provider[key]
    return default


def calculate_final_score(provider: Dict[str, Any], weights: Optional[Dict[str, float]] = None, debug: bool = False) -> Dict[str, Any]:
    """Calculate a final provider score and attach it to the provider dict."""
    weights = normalize_weights(weights)

    ml_score = normalize_score(get_first_available(provider, ['ml_score']))
    system_score = normalize_score(get_first_available(provider, ['system_score', 'system_ranking_score']))
    admin_score = normalize_score(get_first_available(provider, ['admin_score', 'admin_ranking_score']))

    final_score = (
        ml_score * weights['ml'] +
        system_score * weights['system'] +
        admin_score * weights['admin']
    )

    final_score = round(max(0.0, min(100.0, final_score)), 2)

    result = dict(provider)
    result['final_score'] = final_score

    if debug:
        result['_final_ranking_debug'] = {
            'weights': weights,
            'ml_score': ml_score,
            'system_score': system_score,
            'admin_score': admin_score,
            'final_score_raw': final_score,
        }

    return result


def sort_providers_by_final_score(providers: Iterable[Dict[str, Any]], weights: Optional[Dict[str, float]] = None) -> List[Dict[str, Any]]:
    """Sort providers descending by computed final_score."""
    scored = [calculate_final_score(provider, weights) for provider in providers]
    return sorted(scored, key=lambda p: p['final_score'], reverse=True)


def update_all_provider_scores(conn, weights: Optional[Dict[str, float]] = None) -> Dict[int, float]:
    """Compute and optionally persist final scores for all providers."""
    cursor = conn.cursor()
    cursor.execute("SHOW COLUMNS FROM service_providers")
    rows = cursor.fetchall()
    columns = [row[0] for row in rows]

    select_fields = ['id']
    if 'ml_score' in columns:
        select_fields.append('ml_score')
    if 'system_score' in columns:
        select_fields.append('system_score')
    if 'system_ranking_score' in columns:
        select_fields.append('system_ranking_score')
    if 'admin_score' in columns:
        select_fields.append('admin_score')
    if 'admin_ranking_score' in columns:
        select_fields.append('admin_ranking_score')

    cursor.execute(f"SELECT {', '.join(select_fields)} FROM service_providers")
    rows = cursor.fetchall()
    columns = [col[0] for col in cursor.description]

    results: Dict[int, float] = {}
    final_score_column = 'final_score'

    cursor.execute("SHOW COLUMNS FROM service_providers LIKE %s", (final_score_column,))
    has_column = bool(cursor.fetchone())

    update_stmt = None
    if has_column:
        update_stmt = conn.cursor()

    for row in rows:
        provider = dict(zip(columns, row))
        scored = calculate_final_score(provider, weights)
        results[int(provider['id'])] = scored['final_score']
        if has_column and update_stmt is not None:
            update_stmt.execute("UPDATE service_providers SET final_score = %s WHERE id = %s", (scored['final_score'], provider['id']))

    if has_column:
        conn.commit()

    return results


if __name__ == '__main__':
    provider_sample = {
        'ml_score': 87,
        'system_score': 76,
        'admin_score': 45,
    }
    print(calculate_final_score(provider_sample))
