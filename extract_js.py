import re

with open('/home/dashboard/public_html/presentation/index.html', 'r', encoding='utf-8') as f:
    content = f.read()

scripts = re.findall(r'<script>(.*?)</script>', content, re.DOTALL)
for i, s in enumerate(scripts):
    with open(f'/tmp/script_{i}.js', 'w', encoding='utf-8') as sf:
        sf.write(s)
