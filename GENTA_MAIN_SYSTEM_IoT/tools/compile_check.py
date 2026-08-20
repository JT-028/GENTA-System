import sys
p = r"c:\Users\vonti\OneDrive\Desktop\GENTA SYS\GENTA7.py"
try:
    with open(p, 'r', encoding='utf-8') as f:
        src = f.read()
    compile(src, p, 'exec')
    print('COMPILE_OK')
except Exception as e:
    print('COMPILE_ERROR')
    import traceback
    traceback.print_exc()
    sys.exit(1)
