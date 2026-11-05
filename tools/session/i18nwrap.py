import sys,re,json,os
"""usage: i18nwrap.py <view relative to resources/views, no .blade.php> ; wraps title/desc in __() and content in an ES include"""
R='/var/www/vhosts/dev.udshafts.com/uds-app/resources/views/'
name=sys.argv[1]; p=R+name+'.blade.php'; s=open(p).read()
if "i18n-content." in s: print('already wrapped', name); sys.exit()
keys=[]
def sec(m):
    k=m.group(2); keys.append(k)
    return "@section('%s', __(%s))" % (m.group(1), "'"+k.replace("'","\\'")+"'")
s=re.sub(r"@section\('(title|desc)','((?:[^'\\]|\\.)*)'\)", lambda m: sec(m), s)
inc=name.replace('/','.')
i=s.index("@section('content')")+len("@section('content')")
j=s.rindex('@endsection')
s=s[:i]+"\n@if(app()->getLocale()==='es')\n@include('i18n-content.%s')\n@else"%inc+s[i:j]+"@endif\n"+s[j:]
open(p,'w').write(s)
print(json.dumps(keys, ensure_ascii=False))
