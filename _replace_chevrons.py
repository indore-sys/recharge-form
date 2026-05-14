# -*- coding: utf-8 -*-
p = r"c:\Users\admin\Downloads\recharge-new-2026\recharge-form\client-requirement-form.html"
s = open(p, encoding="utf-8").read()
chev = '<span class="section-toggle" aria-hidden="true"><svg class="section-toggle-icon" width="20" height="20" focusable="false"><use href="#i-chevron"></use></svg></span>'
s = s.replace('<span class="section-toggle">▼</span>', chev)
s = s.replace('<span class="section-toggle" aria-hidden="true">▼</span>', chev)
open(p, "w", encoding="utf-8").write(s)
print("chevrons", chev[:40])
