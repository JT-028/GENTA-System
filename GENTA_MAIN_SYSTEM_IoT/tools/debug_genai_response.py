import json, sys, os

def dump(o):
    try:
        return json.dumps(o, default=lambda x: getattr(x, '__dict__', str(x)), indent=2)
    except Exception:
        return repr(o)

try:
    try:
        import google.generativeai as genai
    except Exception:
        # local shim fallback
        import google_generative_shim as genai

    genai.configure(api_key=os.environ.get('GENAI_API_KEY', ''))
    model = genai.GenerativeModel(model_name='gemini-2.5-flash')
    chat = model.start_chat()
    print('Sending test prompt: "What is addition"')
    resp = chat.send_message('What is addition', generation_config={'max_output_tokens':100, 'temperature':1.0})
    print('\n--- RESPONSE REPR ---')
    print(repr(resp))
    print('\n--- ATTRS ---')
    for a in dir(resp):
        if a.startswith('__'):
            continue
        try:
            v = getattr(resp, a)
            print(f"{a}: {type(v)}")
        except Exception as e:
            print(f"{a}: <error retrieving attribute: {e}>")
    print('\n--- CANDIDATES DETAIL ---')
    try:
        cands = getattr(resp, 'candidates', None)
        print('candidates type:', type(cands))
        if cands:
            print('candidates count:', len(cands))
            for i, cand in enumerate(cands):
                print(f'--- candidate {i} repr (truncated) ---')
                s = repr(cand)
                print(s[:2000])
                try:
                    print('  finish_reason=', getattr(cand, 'finish_reason', None))
                except Exception:
                    pass
                try:
                    content = getattr(cand, 'content', None)
                    print('  content type:', type(content))
                    if content is not None:
                        parts = getattr(content, 'parts', None)
                        print('  content.parts type:', type(parts))
                        if parts:
                            for j, p in enumerate(parts):
                                print(f'    part {j} repr (short):', repr(p)[:1000])
                                # attempt to access p.text
                                txt = getattr(p, 'text', None)
                                if txt is not None:
                                    print('      part.text:', txt[:400])
                except Exception as e:
                    print('  error inspecting candidate content:', e)
    except Exception as e:
        print('error reading candidates:', e)

    print('\n--- TOP-LEVEL TEXT ---')
    try:
        print('response.text:', getattr(resp, 'text', None))
    except Exception as e:
        print('error getting response.text:', e)

except Exception as e:
    print('Exception during test:', e)
    sys.exit(2)

print('\nDone')
