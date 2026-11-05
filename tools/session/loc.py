import re,sys,glob,os,json
ROOT='/var/www/vhosts/dev.udshafts.com/uds-app/'
files=[f for f in glob.glob(ROOT+'resources/views/**/*.blade.php',recursive=True) if '/admin/' not in f and not f.endswith('emails/order/shop.blade.php')]
files+=glob.glob(ROOT+'lang/i18n/**/*.json',recursive=True)+glob.glob(ROOT+'lang/i18n/**/*.php',recursive=True)
files+=[ROOT+f for f in ['app/Services/CatalogService.php','app/Services/VehiclePages.php','app/Http/Controllers/CommerceController.php','app/Http/Controllers/FeedController.php','app/Support/JsStrings.php','app/Services/RecoveryMailer.php','app/Console/Commands/RecoverySend.php','app/Providers/AppServiceProvider.php','public/js/assistant.js','app/Services/Chatbot/ChatbotService.php','app/Services/OrderMailer.php','app/Http/Controllers/ShopController.php','app/Services/CartService.php']]
R=[
 (r'11611 Prospect (?:Drive|Dr\.?) (?:STE|Suite) 4,?\s*(?:<br>\s*)?Odessa,? FL,? 33556(?:, USA)?', 'Florida, USA'),
 (r'11611 Prospect Dr STE 4 · 33556', ''),
 (r'\(813\) 524-6491 &nbsp;Odessa', '(813) 524-6491'),
 (r'Universal Driveshafts LLC in Odessa', 'Universal Driveshafts LLC'),
 (r'over the counter in Odessa', 'over the counter'),
 (r'Odessa \(OEM & Parts\)', 'OEM & Parts'),
 (r'Odessa \(OEM y repuestos\)', 'OEM y repuestos'),
 (r'\bthe Odessa shop\b', 'our shop'), (r'\bThe Odessa shop\b', 'Our shop'), (r'\bour Odessa(?:, FL)? shop\b', 'our shop'), (r'\bOdessa shop\b', 'shop'),
 (r'\bel taller de Odessa\b', 'nuestro taller'), (r'\bal taller de Odessa\b', 'a nuestro taller'), (r'\bdel taller de Odessa\b', 'de nuestro taller'), (r'\bnuestro taller de Odessa(?:, FL)?\b', 'nuestro taller'), (r'\btaller de Odessa\b', 'nuestro taller'),
 (r'\bmostrador de Odessa\b', 'mostrador'),
 (r'Odessa, Florida', 'Florida'), (r'Odessa, FL', 'Florida'), (r'Odessa FL', 'Florida'),
 (r'\bin Odessa\b', 'in Florida'), (r'\bfrom Odessa\b', 'from Florida'), (r'\bat Odessa\b', 'in Florida'),
 (r'\ben Odessa\b', 'en Florida'), (r'\bdesde Odessa\b', 'desde Florida'),
 (r'Florida, Florida', 'Florida'), (r'in Florida, Florida', 'in Florida'),
]
changed={}
for f in files:
    if not os.path.isfile(f): continue
    s=open(f,encoding='utf-8').read(); o=s
    for a,b in R: s=re.sub(a,b,s)
    if s!=o:
        if f.endswith('.json'):
            try: json.loads(s)
            except Exception as e: print('JSON BROKE',f,e); continue
        if '--dry' not in sys.argv: open(f,'w',encoding='utf-8').write(s)
        changed[f.replace(ROOT,'')]=o.count('Odessa')-s.count('Odessa')
for k,v in sorted(changed.items()): print(v,k)
print(len(changed),'files')
