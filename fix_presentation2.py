import re

with open('/home/dashboard/public_html/presentation/index.html', 'r', encoding='utf-8') as f:
    content = f.read()

replacements = [
    (r'36\.6%', r'35.8%'),
    (r'13\.1%', r'20.3%'),
    (r'288 cancelled', r'293 cancelled'),
    (r'288 annulés', r'293 annulés'),
    (r'786 ordres actifs', r'819 ordres actifs'),
    (r'786 orders actifs', r'819 orders actifs'),
    (r'1,899 cancelled', r'2,081 cancelled'),
    (r'7,117 total orders', r'7,794 total orders'),
    (r'Total H1=498', r'Total H1=499'),
    (r'911 total orders H1', r'819 total orders H1'),
    (r'786 actifs = 499 CMD_Done \+ 288 annulés \+ 125 pending', r'819 actifs = 499 CMD_Done + 293 annulés + 27 pending'),
    (r'183/498', r'183/499'),
    (r'498 CMD_Done', r'499 CMD_Done'),
]

for old, new in replacements:
    content = re.sub(old, new, content)

with open('/home/dashboard/public_html/presentation/index.html', 'w', encoding='utf-8') as f:
    f.write(content)
