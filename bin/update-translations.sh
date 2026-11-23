#!/usr/bin/env bash
set -e
# Regenerates the .pot from the code (plus plugin header strings), merges it
# into the Dutch .po and lists untranslated msgids. Needs gettext.
# Also rebuilds the .mo. Fill in any listed msgstrs in the .po and run it again.

cd "$(dirname "$0")/.."
L=languages; D=brew-log-for-brewfather
T=$(mktemp -d)
xgettext --language=PHP --from-code=UTF-8 --add-comments=translators: --no-wrap \
  --keyword=__ --keyword=_e --keyword=esc_html__ --keyword=esc_html_e --keyword=esc_attr__ --keyword=esc_attr_e \
  --keyword=_x:1,2c --keyword=esc_html_x:1,2c --keyword=esc_attr_x:1,2c --keyword=_n:1,2 --keyword=_nx:1,2,4c \
  -o $T/code.pot $(git ls-files '*.php' | grep -v '^tests/'; git ls-files --others --exclude-standard '*.php' | grep -v '^tests/') 2>/dev/null
cat > $T/header.pot <<'H'
msgid ""
msgstr ""
"Content-Type: text/plain; charset=UTF-8\n"

#. Plugin Name of the plugin
msgid "Brew Log for Brewfather"
msgstr ""

#. Description of the plugin
msgid "Show your Brewfather recipes, batches and stock on your WordPress site."
msgstr ""

#. Author of the plugin
msgid "R. de Weerd"
msgstr ""
H
msgcat --use-first --no-wrap --no-location $T/header.pot $T/code.pot -o $T/all.pot
cat > $L/$D.pot <<'H'
# Copyright (C) R. de Weerd
# This file is distributed under the GPL-2.0-or-later.
msgid ""
msgstr ""
"Project-Id-Version: Brew Log for Brewfather\n"
"MIME-Version: 1.0\n"
"Content-Type: text/plain; charset=UTF-8\n"
"Content-Transfer-Encoding: 8bit\n"
"X-Domain: brew-log-for-brewfather\n"

H
sed '1,/^$/d' $T/all.pot >> $L/$D.pot
msgmerge --quiet --no-wrap --no-fuzzy-matching --no-location --backup=none -U $L/$D-nl_NL.po $L/$D.pot
msgattrib --no-obsolete --no-wrap -o $L/$D-nl_NL.po $L/$D-nl_NL.po
echo "untranslated:"; msgattrib --untranslated --no-wrap $L/$D-nl_NL.po | grep -E '^msg(ctxt|id) ' | grep -v 'msgid ""$' || echo "  (none)"
msgfmt --check -o $L/$D-nl_NL.mo $L/$D-nl_NL.po 2>&1 | grep -v 'header field' || true
