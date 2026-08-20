import json, sys, os
try:
    try:
        import google.generativeai as genai
    except Exception:
        import google_generative_shim as genai

    genai.configure(api_key=os.environ.get('GENAI_API_KEY', ''))
    model = genai.GenerativeModel(model_name='gemini-2.5-flash')
    chat = model.start_chat()

    # Read transcript
    p = 'transcribed_text.txt'
    if not os.path.exists(p):
        print('transcript file not found:', p)
        sys.exit(2)
    txt = open(p,'r',encoding='utf-8').read().strip()
    print('Transcript:', txt)

    print('\n--- chat.send_message() ---')
    resp = chat.send_message(txt, generation_config={'max_output_tokens':200, 'temperature':1.0})
    print('repr(resp)[:1000]:')
    print(repr(resp)[:2000])
    print('\nCandidates count:', len(getattr(resp,'candidates',[]) or []))
    try:
        c0 = (getattr(resp,'candidates',[]) or [None])[0]
        print('first candidate finish_reason:', getattr(c0,'finish_reason',None))
        print('first candidate content parts len:', len(getattr(getattr(c0,'content',None),'parts',[]) or []))
    except Exception as e:
        print('error inspecting candidate:', e)

    print('\nTop-level resp.text:', end=' ')
    try:
        print(getattr(resp,'text',None))
    except Exception as e:
        print('error:', e)

    print('\n--- model.generate_content() fallback ---')
    try:
        fb = model.generate_content(txt)
        print('repr(fb)[:2000]:')
        print(repr(fb)[:2000])
        print('fb candidates count:', len(getattr(fb,'candidates',[]) or []))
        try:
            f0 = (getattr(fb,'candidates',[]) or [None])[0]
            print('fb first candidate content parts len:', len(getattr(getattr(f0,'content',None),'parts',[]) or []))
        except Exception as e:
            print('inspect fb candidate error:', e)
        print('fb top-level text:', getattr(fb,'text',None))
    except Exception as e:
        print('generate_content fallback error:', e)

    print('\nDone')
except Exception as e:
    print('Script error:', e)
    sys.exit(2)
