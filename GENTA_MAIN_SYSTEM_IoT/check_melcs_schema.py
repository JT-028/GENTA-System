import mysql.connector
import sys

try:
    conn = mysql.connector.connect(
        host='localhost',
        database='my_app',
        user='root',
        password=''
    )
    cursor = conn.cursor()
    
    print("\n" + "="*60)
    print("MELCS TABLE SCHEMA")
    print("="*60)
    
    cursor.execute('DESCRIBE melcs')
    rows = cursor.fetchall()
    
    print("\nColumns in 'melcs' table:")
    for row in rows:
        col_name = row[0]
        col_type = row[1]
        nullable = row[2]
        key = row[3]
        default = row[4]
        extra = row[5]
        print(f"  • {col_name:<20} {col_type:<20} NULL:{nullable} KEY:{key}")
    
    print("\n" + "="*60)
    print("SAMPLE DATA (first 2 rows)")
    print("="*60)
    
    cursor.execute('SELECT * FROM melcs LIMIT 2')
    sample_rows = cursor.fetchall()
    
    if sample_rows:
        # Get column names
        cursor.execute('DESCRIBE melcs')
        columns = [row[0] for row in cursor.fetchall()]
        
        for i, sample in enumerate(sample_rows, 1):
            print(f"\nRow {i}:")
            for col_name, val in zip(columns, sample):
                print(f"  {col_name}: {val}")
    else:
        print("  (No data in table)")
    
    cursor.close()
    conn.close()
    
    print("\n" + "="*60 + "\n")
    
except Exception as e:
    print(f"\n❌ ERROR: {e}\n")
    sys.exit(1)
