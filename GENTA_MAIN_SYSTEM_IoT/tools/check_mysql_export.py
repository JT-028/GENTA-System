import os, json, sys
out = {'connector_available': False, 'connected': False, 'error': None, 'host': None, 'user': None, 'database': None, 'tables': [], 'table_checks': {}}
try:
    import mysql.connector as mysql_connector
    out['connector_available'] = True
except Exception as e:
    out['error'] = f'mysql.connector import failed: {e}'
    print(json.dumps(out, indent=2))
    sys.exit(0)

# get env
db_host = os.environ.get('MYSQL_HOST') or os.environ.get('DB_HOST') or 'localhost'
db_user = os.environ.get('MYSQL_USER') or os.environ.get('DB_USER') or 'root'
db_pass = os.environ.get('MYSQL_PASS') or os.environ.get('DB_PASS') or os.environ.get('MYSQL_PASSWORD') or ''
db_name = os.environ.get('MYSQL_DB') or os.environ.get('DB_NAME') or 'my_app'
out['host'] = db_host
out['user'] = db_user
out['database'] = db_name

conn = None
try:
    conn = mysql_connector.connect(host=db_host, user=db_user, password=db_pass, database=db_name, connection_timeout=5)
    out['connected'] = True
except Exception as e:
    out['error'] = f'connect failed: {e}'
    print(json.dumps(out, indent=2))
    sys.exit(0)

try:
    cur = conn.cursor()
    try:
        cur.execute('SHOW TABLES')
        rows = cur.fetchall()
        tables = [r[0] for r in rows]
        out['tables'] = tables
    except Exception as e:
        out['error'] = f'SHOW TABLES failed: {e}'

    # check specific tables from your backup-meta
    targets = ['questions', 'student_quiz_questions', 'users']
    for t in targets:
        info = {'exists': False, 'count': None, 'error': None}
        try:
            if t in tables:
                info['exists'] = True
                try:
                    cur.execute(f"SELECT COUNT(*) FROM `{t}` LIMIT 1")
                    rr = cur.fetchone()
                    info['count'] = int(rr[0]) if rr and rr[0] is not None else 0
                except Exception as e:
                    info['error'] = f'select failed: {e}'
            else:
                info['exists'] = False
        except Exception as e:
            info['error'] = f'check failed: {e}'
        out['table_checks'][t] = info
finally:
    try:
        cur.close()
    except:
        pass
    try:
        conn.close()
    except:
        pass

print(json.dumps(out, indent=2))
