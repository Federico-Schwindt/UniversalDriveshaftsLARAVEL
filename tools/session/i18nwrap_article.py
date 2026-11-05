import sys
R='/var/www/vhosts/dev.udshafts.com/uds-app/resources/views/guides/'
slug=sys.argv[1]; p=R+slug+'.blade.php'; s=open(p).read()
if 'i18n-content.guides.' in s: print('already', slug); sys.exit()
i=s.index("@section('article')")+len("@section('article')")
j=s.rindex('@endsection')
s=s[:i]+"\n@if(app()->getLocale()==='es')\n@include('i18n-content.guides.%s')\n@else"%slug+s[i:j]+"@endif\n"+s[j:]
open(p,'w').write(s); print('wrapped',slug)
