import sys
p='/var/www/vhosts/dev.udshafts.com/uds-app/app/Http/Controllers/AccountController.php'
s=open(p).read()
exec(open(sys.argv[1]).read())
for a,b in R:
    assert s.count(a)==1, a[:80]
    s=s.replace(a,b)
open(p,'w').write(s)
print("ok")
