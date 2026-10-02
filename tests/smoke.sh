#!/usr/bin/env bash
# End-to-end check against wp-env (npx @wordpress/env start). Usage: bash tests/smoke.sh
set -euo pipefail
cd "$(dirname "$0")/.."

URL=http://localhost:8888/wp-json/atelier-mcp/v1/mcp
wpcli() { npx -y @wordpress/env run cli wp "$@" 2>/dev/null | tr -d '\r'; }
fail() { echo "FAIL: $*"; exit 1; }

wpcli option update atelier_mcp_mode approval >/dev/null
PASS=$(wpcli user application-password create admin "smoke-$RANDOM" --porcelain | tr -d '\n')
SID=$(curl -s -D - -o /dev/null -u "admin:$PASS" -H 'Content-Type: application/json' "$URL" \
	-d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"smoke","version":"1"}}}' |
	grep -i mcp-session-id | cut -d' ' -f2 | tr -d '\r')

# call TOOL JSON_ARGS -> prints structuredContent (or the error text, prefixed with ERROR:)
call() {
	curl -s -u "admin:$PASS" -H 'Content-Type: application/json' -H "Mcp-Session-Id: $SID" -H 'MCP-Protocol-Version: 2025-06-18' "$URL" \
		-d "{\"jsonrpc\":\"2.0\",\"id\":2,\"method\":\"tools/call\",\"params\":{\"name\":\"$1\",\"arguments\":$2}}" |
		python -c 'import sys,json; r=json.load(sys.stdin); r=r.get("result",r); print("ERROR:"+json.dumps(r) if r.get("isError") or "error" in r or "structuredContent" not in r else json.dumps(r["structuredContent"]))'
}
get() { python -c "import sys,json; print(json.loads(sys.argv[1])$2)" "$1"; }

echo "1. approval mode: new content is a draft, publish is refused"
R=$(call atelier_create_content '{"title":"Smoke","content":"<!-- wp:paragraph --><p>Hola</p><!-- /wp:paragraph -->"}')
[ "$(get "$R" "['status']")" = draft ] || fail "create: $R"
[[ $(call atelier_create_content '{"title":"X","status":"publish"}') == ERROR* ]] || fail "publish allowed in approval mode"

echo "2. approval mode: editing a published page creates a draft copy"
LIVE=$(wpcli post create --post_type=page --post_status=publish --post_title=Live --post_content=original --porcelain)
R=$(call atelier_update_content "{\"id\":$LIVE,\"content\":\"changed\",\"seo_description\":\"desc\"}")
COPY=$(get "$R" "['id']")
[ "$COPY" != "$LIVE" ] || fail "no copy: $R"
[ "$(wpcli post get "$LIVE" --field=post_content)" = original ] || fail "original changed"

echo "3. approval mode: global styles only as dry_run"
[[ $(call atelier_update_global_styles '{"styles":{"color":{"background":"#111111"}}}') == ERROR* ]] || fail "styles allowed"
[ "$(get "$(call atelier_update_global_styles '{"styles":{"color":{"background":"#111111"}},"dry_run":true}')" "['dry_run']")" = True ] || fail "dry_run"

echo "4. human publishes the draft copy -> applied to the original"
wpcli post update "$COPY" --post_status=publish --user=admin >/dev/null
[ "$(wpcli post get "$LIVE" --field=post_content)" = changed ] || fail "copy not merged"
[ "$(wpcli post get "$COPY" --field=post_status)" = trash ] || fail "copy not trashed"

echo "5. full access: template edit and rollback to theme version"
wpcli option update atelier_mcp_mode full >/dev/null
R=$(call atelier_update_template '{"id":"twentytwentyfive//404","content":"<!-- wp:paragraph --><p>Smoke 404</p><!-- /wp:paragraph -->"}')
CH=$(get "$R" "['change_id']")
call atelier_rollback "{\"change_id\":$CH}" >/dev/null
[ "$(get "$(call atelier_list_templates '{"id":"twentytwentyfive//404"}')" "['source']")" = theme ] || fail "template not reverted"

echo "6. full access: global styles live + rollback"
R=$(call atelier_update_global_styles '{"styles":{"color":{"background":"#123456"}}}')
CH=$(get "$R" "['change_id']")
[[ $(call atelier_get_global_styles '{}') == *123456* ]] || fail "style not applied"
call atelier_rollback "{\"change_id\":$CH}" >/dev/null
[[ $(call atelier_get_global_styles '{}') != *123456* ]] || fail "style not reverted"

echo "7. upload media (base64) and rollback deletes it"
PNG=iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==
R=$(call atelier_upload_media "{\"data\":\"$PNG\",\"filename\":\"smoke.png\",\"alt\":\"Smoke\"}")
MEDIA=$(get "$R" "['id']")
call atelier_rollback "{\"change_id\":$(get "$R" "['change_id']")}" >/dev/null
[ -z "$(wpcli post list --post_type=attachment --post__in="$MEDIA" --format=ids)" ] || fail "media not deleted"

echo "8. rollback of a content edit restores the previous text"
R=$(call atelier_update_content "{\"id\":$LIVE,\"content\":\"third\"}")
call atelier_rollback "{\"change_id\":$(get "$R" "['change_id']")}" >/dev/null
[ "$(wpcli post get "$LIVE" --field=post_content)" = changed ] || fail "content not reverted"

wpcli option update atelier_mcp_mode approval >/dev/null
echo "OK: all checks passed"
