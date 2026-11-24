# Directrices de Desarrollo y Despliegue

## Regla de Despliegue Obligatoria por Defecto
Cada vez que se realice cualquier modificación, corrección o mejora en este repositorio:
1. **Comprometer y subir los cambios a `ars2.0`:**
   - Hacer `git add`, `git commit` y `git push origin main` y `git push origin main:gh-pages`.
   - Web disponible directamente en: https://dga80.github.io/ars2.0/
2. **Sincronizar y desplegar automáticamente en `instaleads`:**
   - Sincronizar siempre los archivos modificados con `/Users/danidev/Proyectos/instaleads` en la rama `gh-pages` en la carpeta `ars-preview/`.
   - Hacer `git add`, `git commit` y `git push origin gh-pages`.
   - Esperar/verificar que GitHub Pages termine de publicar la web en https://dga80.github.io/instaleads/ars-preview/.
   - Nunca dar una tarea por finalizada sin haber realizado este proceso completo de subida y despliegue por defecto.

## Arquitectura del Asistente Legal (Chatbot IA)
- **Hosting de producción (`arsadvocats.com`):** IONOS (hosting compartido Apache + PHP-FPM con *output buffering*).
- **Backend del chatbot (preferido):** Vercel Edge Function (`/api/chat`) con la variable secreta `GEMINI_API_KEY` en Vercel Environment Variables (nunca en el repositorio ni en el FTP de IONOS). Evitar Cloudflare Workers (`*.workers.dev`) por los bloqueos de IPs compartidas en operadoras españolas.
- **Despliegue en IONOS:** subir únicamente `chatbot.css` y `chatbot.js` junto al resto de la web apuntando al endpoint `/api/chat` en Vercel (solo usar `chat.php` en IONOS si el cliente exige explícitamente cero servicios externos).
- **Privacidad y RGPD:** usar `sessionStorage` (nunca `localStorage` persistente) para la conversación y activar facturación *Pay-as-you-go* en Google AI Studio con la cuenta del cliente al pasar a producción.
- **Entorno de preview del chatbot:** carpeta `preview-chatbotAgent/` en `instaleads` (rama `gh-pages`), publicada en https://instaleads-blond.vercel.app/ y https://dga80.github.io/instaleads/preview-chatbotAgent/.
