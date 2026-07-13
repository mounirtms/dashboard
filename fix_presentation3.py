import re

with open('/home/dashboard/public_html/presentation/index.html', 'r', encoding='utf-8') as f:
    content = f.read()

replacements = [
    (r'2,215 commits', r'4,593 commits'),
    (r'2,215 GitLab commits', r'4,593 GitLab commits'),
    (r'2,215 total commits', r'4,593 total commits'),
    (r'>1,859<', r'>3,913<'),
    (r'1,859 GitLab', r'3,913 GitLab'),
    (r'120 \(H1 2025\)', r'240 (H1 2025)'),
    (r'120 H1 2025', r'240 H1 2025'),
    (r'>120<', r'>240<'),
    (r'\+1,449%', r'+1,530%'),
    (r'\+1449%', r'+1530%'),
    (r'445→498', r'445→499'),
    (r'11\.9%', r'12.1%'),
    (r'498 CMD_Done', r'499 CMD_Done'),
    (r'498 orders', r'499 orders'),
    (r'183/498', r'183/499'),
    (r'>498<', r'>499<'),
    (r'2\.78M', r'2.79M'),
    (r'\+0\.9%', r'+1.0%'),
    (r'>5,591<', r'>5,585<'),
    (r'5,591 DZD', r'5,585 DZD'),
    (r'−9\.8%', r'−9.9%'),
    (r'13\.1%→36\.6%', r'20.3%→35.8%'),
    (r'13\.1%<', r'20.3%<'),
    (r'36\.6%<', r'35.8%<'),
    (r'36\.6%', r'35.8%'),
    (r'9,275', r'9,277'),
    (r'4,484', r'4,490'),
    (r'dev=1,735', r'dev=1,739'),
    (r'dev:1,735', r'dev:1,739'),
    (r'Jul 11, 2026', r'Jul 12, 2026'),
    (r'MounirAb 98\.9% \(2,191\)', r'Mounir 92.0% (4,227)'),
    (r'MounirAb=2,191 \(98\.9%\)', r'Mounir=4,227 (92.0%)'),
    (r'MounirAb 98\.9%', r'Mounir 92.0%'),
    (r'288 cancelled', r'293 cancelled'),
    (r'288 annulés', r'293 annulés'),
    (r'786 ordres actifs', r'819 ordres actifs'),
    (r'786 orders actifs', r'819 orders actifs'),
    (r'1,899 cancelled', r'2,081 cancelled'),
    (r'7,117 total orders', r'7,794 total orders'),
    (r'Total H1=498', r'Total H1=499'),
    (r'911 total orders H1', r'819 total orders H1'),
    (r'786 actifs = 499 CMD_Done \+ 288 annulés \+ 125 pending', r'819 actifs = 499 CMD_Done + 293 annulés + 27 pending')
]

for old, new in replacements:
    content = re.sub(old, new, content)

with open('/home/dashboard/public_html/presentation/index.html', 'w', encoding='utf-8') as f:
    f.write(content)
