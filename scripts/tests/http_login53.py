"""Real HTTP admin login for a disposable CI site; never print passwords/tokens."""
from http.cookiejar import CookieJar
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlencode, urlsplit
from urllib.request import build_opener, HTTPCookieProcessor

class Form(HTMLParser):
    token = None
    def handle_starttag(self, tag, attrs):
        attrs=dict(attrs)
        if tag=='input' and attrs.get('name')=='logintoken':self.token=attrs.get('value')

settings=dict(line.split('=',1) for line in Path('.env53').read_text().splitlines() if '=' in line)
base='http://localhost:'+settings['MOODLE_PORT']
client=build_opener(HTTPCookieProcessor(CookieJar()))
with client.open(base+'/login/index.php',timeout=30) as response:page=response.read().decode()
form=Form();form.feed(page)
assert form.token,'No login token in HTTP form'
data=urlencode(dict(username='admin',password=settings['ADMIN_PASSWORD'],logintoken=form.token)).encode()
with client.open(base+'/login/index.php',data=data,timeout=60) as response:
    target=response.geturl();html=response.read().decode()
assert '/login/index.php' not in urlsplit(target).path,'Admin login did not redirect'
assert '/login/logout.php' in html,'No authenticated logout link'
print('PASS: real Moodle 5.3 HTTP admin login (not a browser rendering test)')
