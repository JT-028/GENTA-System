import re
import html

SAMPLE_PATH = r"c:\Users\vonti\OneDrive\Desktop\GENTA SYS\MAIN_SYSTEM\uploads\tailored_module_Jonathan_Tiglao_107048090462.txt"


def sanitize_module_text(text: str) -> str:
    if not text:
        return text
    try:
        text = html.unescape(text)
        text = re.sub(r'```[A-Za-z0-9_-]*\n', '', text)
        text = text.replace('```', '')
        text = re.sub(r'<\s*br\s*/?>', '\n', text, flags=re.I)
        text = re.sub(r'<[^>]+>', '', text)
        text = text.replace('**', '').replace('__', '')
        # Remove leading Markdown header hashes (#, ##, ###) at start of lines
        text = re.sub(r'^[ \t]*#{1,6}\s*', '', text, flags=re.M)
        text = '\n'.join([ln.rstrip() for ln in text.splitlines()])
        text = re.sub(r'\n{3,}', '\n\n', text)
        return text.strip()
    except Exception:
        return text


if __name__ == '__main__':
    try:
        with open(SAMPLE_PATH, 'r', encoding='utf-8') as f:
            s = f.read()
    except Exception as e:
        print('Could not open sample file:', e)
        raise

    out = sanitize_module_text(s)
    # Print first 4000 characters
    print(out[:4000])
