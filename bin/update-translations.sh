#!/usr/bin/env bash
set -e
# Regenerates the .pot from the code (plus plugin header strings), merges it
# into the Dutch .po and lists untranslated msgids. Needs gettext.
# Fill in the listed msgstrs in the .po, then rebuild the .mo:
#   msgfmt -o languages/brew-log-for-brewfather-nl_NL.mo languages/brew-log-for-brewfather-nl_NL.po

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
msgid "MythoTec"
msgstr ""
H
msgcat --use-first --no-wrap --no-location $T/header.pot $T/code.pot -o $T/all.pot
# Keep the committed header block of the .pot.
{ sed -n '1,/^$/p' $L/$D.pot; sed '1,/^$/d' $T/all.pot; } > $L/$D.pot
msgmerge --quiet --no-wrap --no-fuzzy-matching --no-location --backup=none -U $L/$D-nl_NL.po $L/$D.pot
msgattrib --no-obsolete --no-wrap -o $L/$D-nl_NL.po $L/$D-nl_NL.po
echo "untranslated:"; msgattrib --untranslated --no-wrap $L/$D-nl_NL.po | grep -E '^msg(ctxt|id) ' | grep -v 'msgid ""$'
