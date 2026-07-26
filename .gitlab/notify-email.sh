#!/usr/bin/env bash
# =============================================================
# KeyHome CI — Notification email (Resend)
# =============================================================
#
# Envoie un email pro a la fin de chaque pipeline deployment, en
# distinguant succes (banner vert) et echec (banner rouge + nom du
# job qui a fail).
#
# Appele depuis `.gitlab-ci.yml` jobs `email_success` / `email_failure`.
# Tous les inputs viennent des variables d'env GitLab (CI_* + DEPLOY_*).
#
# Required env vars (settees par le job YAML) :
#   RESEND_API_KEY        — cle API Resend (Settings → CI/CD → Variables)
#   DEPLOY_NOTIFY_EMAIL   — destinataire(s), CSV
#   EMAIL_STATUS          — "success" | "failure"
#
# Optional :
#   DEPLOY_NOTIFY_FROM    — expediteur (default ci@keyhome.app)
#
# Aucun set -e : un email rate ne DOIT PAS faire echouer la pipeline
# (allow_failure: true au niveau du job en plus, double protection).

# ── Helpers ───────────────────────────────────────────────────────
SHORT_SHA="${CI_COMMIT_SHA:0:8}"
COMMIT_URL="${CI_PROJECT_URL}/-/commit/${CI_COMMIT_SHA}"
PIPELINE_URL="${CI_PROJECT_URL}/-/pipelines/${CI_PIPELINE_ID}"
LOGS_URL="${CI_PROJECT_URL}/-/pipelines/${CI_PIPELINE_ID}/failures"
TIMESTAMP=$(date -u +"%d/%m/%Y a %H:%M UTC")

# ── Environnement ────────────────────────────────────────────────
if [ "$CI_COMMIT_BRANCH" = "main" ]; then
  DEPLOY_ENV="Production"
  DEPLOY_URL="https://api.keyhome.app"
else
  DEPLOY_ENV="Pre-production"
  DEPLOY_URL="https://preprod.api.keyhome.app"
fi

# ── Status (success / failure) ───────────────────────────────────
FAILED_JOB=""
FAILED_STAGE=""

if [ "$EMAIL_STATUS" = "success" ]; then
  STATUS_EMOJI="✅"
  STATUS_TITLE="Deploiement reussi"
  STATUS_SUBTITLE="L'application a ete deployee et le smoke test a passe."
  STATUS_COLOR="#16A34A"
  STATUS_COLOR_DARK="#15803D"
  SUBJECT_PREFIX="✅"
  SUBJECT_VERB="reussi"
else
  STATUS_EMOJI="🚨"
  STATUS_TITLE="Pipeline echouee"
  STATUS_SUBTITLE="Un job de la pipeline n'a pas abouti — l'application n'a PAS ete deployee."
  STATUS_COLOR="#DC2626"
  STATUS_COLOR_DARK="#B91C1C"
  SUBJECT_PREFIX="🚨"
  SUBJECT_VERB="echouee"

  # Recuperer le nom du premier job qui a fail via l'API GitLab
  FAILED_JSON=$(curl --globoff -sS --max-time 8 \
    --header "JOB-TOKEN: $CI_JOB_TOKEN" \
    "$CI_API_V4_URL/projects/$CI_PROJECT_ID/pipelines/$CI_PIPELINE_ID/jobs?scope%5B%5D=failed" 2>/dev/null || echo "[]")
  FAILED_JOB=$(echo "$FAILED_JSON" | grep -o '"name":"[^"]*"' | head -1 | sed 's/"name":"//;s/"$//')
  FAILED_STAGE=$(echo "$FAILED_JSON" | grep -o '"stage":"[^"]*"' | head -1 | sed 's/"stage":"//;s/"$//')
  [ -z "$FAILED_JOB" ] && FAILED_JOB="(inconnu)"
  [ -z "$FAILED_STAGE" ] && FAILED_STAGE="(inconnu)"
fi

# ── Subject ──────────────────────────────────────────────────────
SUBJECT="${SUBJECT_PREFIX} KeyHome ${DEPLOY_ENV} — Pipeline ${SUBJECT_VERB} (${CI_COMMIT_REF_NAME} · ${SHORT_SHA})"

# ── Optional failure row ─────────────────────────────────────────
FAILURE_ROW=""
if [ "$EMAIL_STATUS" = "failure" ]; then
  FAILURE_ROW="<tr><td style=\"padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:13px;color:#6B7280;font-weight:600;width:140px;vertical-align:top;\">Job echoue</td><td style=\"padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:14px;color:#DC2626;font-weight:800;\"><code style=\"background:#FEE2E2;padding:3px 8px;border-radius:4px;font-family:'SF Mono',Monaco,Consolas,monospace;font-size:12.5px;\">${FAILED_JOB}</code> <span style=\"font-size:12px;color:#9CA3AF;font-weight:500;\">(stage : ${FAILED_STAGE})</span></td></tr>"
fi

# ── Optional secondary CTA (logs link for failure) ───────────────
SECONDARY_CTA=""
if [ "$EMAIL_STATUS" = "failure" ]; then
  SECONDARY_CTA="<td style=\"padding-left:10px;\"><a href=\"${LOGS_URL}\" style=\"display:inline-block;background:#FFFFFF;color:#374151;border:1px solid #D1D5DB;font-size:14px;font-weight:700;text-decoration:none;padding:11px 22px;border-radius:10px;\">Voir les logs</a></td>"
else
  SECONDARY_CTA="<td style=\"padding-left:10px;\"><a href=\"${DEPLOY_URL}\" style=\"display:inline-block;background:#FFFFFF;color:#374151;border:1px solid #D1D5DB;font-size:14px;font-weight:700;text-decoration:none;padding:11px 22px;border-radius:10px;\">Ouvrir ${DEPLOY_ENV}</a></td>"
fi

# ── HTML body (table-based pour Outlook compat) ──────────────────
# Fichier intermediaire pour eviter d'escaper l'HTML dans bash.
HTML_FILE="/tmp/notify-email-${CI_JOB_ID}.html"
cat > "$HTML_FILE" <<HTMLEOF
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>KeyHome CI</title>
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#F3F4F6;color:#1F2937;-webkit-font-smoothing:antialiased;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#F3F4F6;padding:32px 16px;">
<tr><td align="center">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);">

    <!-- ── Status banner ───────────────────────────────── -->
    <tr><td style="background:${STATUS_COLOR};padding:28px 32px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
          <td style="font-size:26px;font-weight:900;color:#FFFFFF;letter-spacing:-0.4px;line-height:32px;">
            ${STATUS_EMOJI} ${STATUS_TITLE}
          </td>
          <td align="right" style="font-size:11px;color:rgba(255,255,255,0.85);font-weight:800;letter-spacing:1.4px;text-transform:uppercase;vertical-align:top;padding-top:6px;">
            KeyHome CI
          </td>
        </tr>
        <tr><td colspan="2" style="font-size:13px;color:rgba(255,255,255,0.92);padding-top:6px;font-weight:500;line-height:18px;">
          ${STATUS_SUBTITLE}
        </td></tr>
      </table>
    </td></tr>

    <!-- ── Metadata table ──────────────────────────────── -->
    <tr><td style="padding:24px 32px 8px 32px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">

        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:13px;color:#6B7280;font-weight:600;width:140px;vertical-align:top;">Environnement</td>
          <td style="padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:14px;color:#111827;font-weight:800;">${DEPLOY_ENV}</td>
        </tr>

        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:13px;color:#6B7280;font-weight:600;vertical-align:top;">Branche</td>
          <td style="padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:14px;color:#111827;font-weight:700;">
            <code style="background:#F3F4F6;padding:3px 8px;border-radius:4px;font-family:'SF Mono',Monaco,Consolas,monospace;font-size:12.5px;">${CI_COMMIT_REF_NAME}</code>
          </td>
        </tr>

        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:13px;color:#6B7280;font-weight:600;vertical-align:top;">Commit</td>
          <td style="padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:14px;color:#111827;font-weight:700;">
            <a href="${COMMIT_URL}" style="color:#F6475F;text-decoration:none;font-weight:800;">
              <code style="background:#F3F4F6;padding:3px 8px;border-radius:4px;font-family:'SF Mono',Monaco,Consolas,monospace;font-size:12.5px;">${SHORT_SHA}</code>
            </a>
          </td>
        </tr>

        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:13px;color:#6B7280;font-weight:600;vertical-align:top;">Auteur</td>
          <td style="padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:14px;color:#111827;font-weight:700;">${GITLAB_USER_NAME:-Inconnu}</td>
        </tr>

        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:13px;color:#6B7280;font-weight:600;vertical-align:top;">Message</td>
          <td style="padding:10px 0;border-bottom:1px solid #E5E7EB;font-size:13.5px;color:#374151;line-height:19px;font-style:italic;">${CI_COMMIT_TITLE}</td>
        </tr>

        ${FAILURE_ROW}

        <tr>
          <td style="padding:10px 0;font-size:13px;color:#6B7280;font-weight:600;vertical-align:top;">Date</td>
          <td style="padding:10px 0;font-size:14px;color:#111827;font-weight:600;">${TIMESTAMP}</td>
        </tr>

      </table>
    </td></tr>

    <!-- ── CTA buttons ─────────────────────────────────── -->
    <tr><td style="padding:16px 32px 28px 32px;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
          <td>
            <a href="${PIPELINE_URL}" style="display:inline-block;background:#F6475F;color:#FFFFFF;font-size:14px;font-weight:800;text-decoration:none;padding:12px 24px;border-radius:10px;letter-spacing:-0.2px;">
              Voir la pipeline →
            </a>
          </td>
          ${SECONDARY_CTA}
        </tr>
      </table>
    </td></tr>

    <!-- ── Footer ──────────────────────────────────────── -->
    <tr><td style="background:#F9FAFB;padding:16px 32px;border-top:1px solid #E5E7EB;">
      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
        <tr>
          <td style="font-size:11px;color:#9CA3AF;font-weight:500;">
            Pipeline #${CI_PIPELINE_ID} · KeyHome Backend
          </td>
          <td align="right" style="font-size:11px;color:#9CA3AF;font-weight:600;">
            <a href="${CI_PROJECT_URL}" style="color:#9CA3AF;text-decoration:none;">Neocraft Team</a>
          </td>
        </tr>
      </table>
    </td></tr>

  </table>

  <!-- ── Tagline hors-card ──────────────────────────────── -->
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;margin-top:18px;">
    <tr><td align="center" style="font-size:11px;color:#9CA3AF;font-weight:500;">
      Vous recevez ce mail car vous etes dans la liste DEPLOY_NOTIFY_EMAIL du projet GitLab.
    </td></tr>
  </table>

</td></tr>
</table>
</body>
</html>
HTMLEOF

# ── Build JSON payload (python3 pour escape safe) ────────────────
PAYLOAD_FILE="/tmp/notify-email-payload-${CI_JOB_ID}.json"
FROM="${DEPLOY_NOTIFY_FROM:-KeyHome CI <ci@keyhome.app>}"

export FROM SUBJECT RECIPIENTS="$DEPLOY_NOTIFY_EMAIL"
python3 - <<'PYEOF' > "$PAYLOAD_FILE"
import json, os
from pathlib import Path

job_id = os.environ.get('CI_JOB_ID', '0')
html = Path(f'/tmp/notify-email-{job_id}.html').read_text(encoding='utf-8')

print(json.dumps({
    'from': os.environ['FROM'],
    'to': [e.strip() for e in os.environ['RECIPIENTS'].split(',') if e.strip()],
    'subject': os.environ['SUBJECT'],
    'html': html,
}, ensure_ascii=False))
PYEOF

# ── Send via Resend ──────────────────────────────────────────────
HTTP_CODE=$(curl --globoff -sS \
  -o /tmp/notify-email-response-${CI_JOB_ID}.txt \
  -w "%{http_code}" \
  -X POST "$RESEND_ENDPOINT" \
  -H "Authorization: Bearer ${RESEND_API_KEY}" \
  -H "Content-Type: application/json" \
  --data-binary "@${PAYLOAD_FILE}" 2>/dev/null || echo "000")

RESPONSE=$(cat /tmp/notify-email-response-${CI_JOB_ID}.txt 2>/dev/null || echo "")

if [ "$HTTP_CODE" = "200" ] || [ "$HTTP_CODE" = "202" ]; then
  echo "✅ Email envoye a : ${DEPLOY_NOTIFY_EMAIL}"
  echo "   Subject : ${SUBJECT}"
else
  echo "⚠  Echec envoi email (HTTP ${HTTP_CODE})"
  echo "   Reponse Resend : ${RESPONSE}"
  echo "   (allow_failure: true sur le job → la pipeline reste OK)"
fi

# Cleanup
rm -f "$HTML_FILE" "$PAYLOAD_FILE" "/tmp/notify-email-response-${CI_JOB_ID}.txt"
exit 0
