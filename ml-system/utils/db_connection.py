"""
db_connection.py
----------------
Centralised database connection helper for Python ML utilities.
Import and use `get_connection()` wherever you need a DB cursor.

Usage:
    from utils.db_connection import get_connection

    conn = get_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT ...")
    rows = cursor.fetchall()
    conn.close()
"""

import os
import mysql.connector
from mysql.connector import Error


# ── Configuration  (override via environment variables in production) ─────────
DB_CONFIG = {
    "host":     os.getenv("DB_HOST",   "localhost"),
    "database": os.getenv("DB_NAME",   "bii_localfinder"),   # <-- update
    "user":     os.getenv("DB_USER",   "gentil"),       # <-- update
    "password": os.getenv("DB_PASS",   "Dushime330805"),   # <-- update
    "charset":  "utf8mb4",
    "use_unicode": True,
    "connection_timeout": 10,
}


def get_connection():
    """
    Returns an open MySQL connection.
    Raises RuntimeError if the connection cannot be established.
    """
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        if conn.is_connected():
            return conn
        raise RuntimeError("Connection object is not connected.")
    except Error as e:
        raise RuntimeError(f"MySQL connection failed: {e}") from e


def test_connection() -> bool:
    """Quick connectivity check — returns True on success."""
    try:
        conn = get_connection()
        conn.close()
        return True
    except RuntimeError:
        return False


# ── CLI quick-test ─────────────────────────────────────────────────────────────
if __name__ == "__main__":
    if test_connection():
        print("[OK] Database connection successful.")
    else:
        print("[FAIL] Could not connect to the database.")