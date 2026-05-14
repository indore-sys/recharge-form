# -*- coding: utf-8 -*-
mainp = r"c:\Users\admin\Downloads\recharge-new-2026\recharge-form\client-requirement-form.html"
sprp = r"c:\Users\admin\Downloads\recharge-new-2026\recharge-form\_sprite_indented.txt"
main = open(mainp, encoding="utf-8").read()
spr = open(sprp, encoding="utf-8").read().rstrip()
old = '<body class="layout--no-sidebar-nav">\n    <div class="container">'
new = '<body class="layout--no-sidebar-nav">\n' + spr + "\n    <div class=\"container\">"
if old not in main:
    raise SystemExit("anchor missing")
open(mainp, "w", encoding="utf-8").write(main.replace(old, new, 1))
print("inserted", len(spr))
