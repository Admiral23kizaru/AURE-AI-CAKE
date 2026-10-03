<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/auth.php';
require_once __DIR__ . '/inc/env.php';
require_customer();

// helper - returns a usable URL/path for an <img src=>
function cake_image($path){
    if (!$path) return 'assets/img/cake-placeholder.png';

    // If it's already a full URL, return as-is
    if (preg_match('#^https?://#i', $path)) return $path;

    // If the stored value contains no slash it is treated as an AI filename
    // stored like: ai_1764056131_059369.png -> map to uploads/ai-cakes/<filename>
    if (strpos($path, '/') === false) {
        return 'uploads/ai-cakes/' . $path;
    }

    // If it already starts with uploads/ return as-is (keep existing relative paths)
    if (strpos($path, 'uploads/') === 0) {
        return $path;
    }

    // Otherwise assume it's a relative path under uploads/
    return 'uploads/' . ltrim($path, '/');
}

// --- Configuration: set your Stability API key here (server-side only) ---
$STABILITY_API_KEY = (string) env_value('STABILITY_API_KEY', '');
$engineId = 'stable-diffusion-xl-1024-v1-0';
$apiHost  = 'https://api.stability.ai';

// Helper: generate 6-digit order number (returns string)
function generate_unique_ai_order_number(PDO $pdo): string {
    for($i=0;$i<20;$i++){
        $candidate=generate_order_number_6();
        $q=$pdo->prepare('SELECT 1 FROM ai_cake_orders WHERE order_number=? UNION SELECT 1 FROM order_headers WHERE order_number=? LIMIT 1');
        $q->execute([$candidate,$candidate]);
        if(!$q->fetchColumn()) return $candidate;
    }
    throw new RuntimeException('Unable to create a unique AI order number. Please try again.');
}

function generate_order_number_6(){
    try {
        $n = random_int(0, 999999);
    } catch (Exception $e) {
        $n = mt_rand(0, 999999);
    }
    return str_pad((string)$n, 6, '0', STR_PAD_LEFT);
}

// AJAX endpoints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    require_post_csrf();
    $action = $_POST['action'] ?? 'generate';

    // Browser-side confirmation is forbidden; staff status changes use the secured workflow.
    if ($action === 'confirm_upload') { http_response_code(403); echo json_encode(['success'=>false,'error'=>'Staff confirmation is required.']); exit; }

    // -- NEW: save_and_chat: save the provided base64 image to disk and insert DB row --
    if ($action === 'save_and_chat') {
        // Expecting: image_b64, prompt, customer_name
        $b64 = $_POST['image_b64'] ?? '';
        $prompt = trim((string)($_POST['prompt'] ?? ''));
        $customer_name = current_user()['name'];

        if ($b64 === '') {
            echo json_encode(['success' => false, 'error' => 'No image data provided.']);
            exit;
        }
        if ($customer_name === '') {
            echo json_encode(['success' => false, 'error' => 'Customer name required.']);
            exit;
        }
        if ($prompt === '' || mb_strlen($prompt) > 1000) {
            echo json_encode(['success' => false, 'error' => 'Enter a prompt of no more than 1,000 characters.']);
            exit;
        }

        // Strip data URL prefix if present
        if (strpos($b64, 'data:image') === 0) {
            $parts = explode(',', $b64, 2);
            if (count($parts) === 2) $b64 = $parts[1];
        }

        $decoded = base64_decode($b64, true);
        $imageInfo = $decoded === false ? false : @getimagesizefromstring($decoded);
        if (
            $decoded === false
            || strlen($decoded) < 1
            || strlen($decoded) > 8 * 1024 * 1024
            || !$imageInfo
            || ($imageInfo['mime'] ?? '') !== 'image/png'
            || ($imageInfo[0] ?? 0) < 1
            || ($imageInfo[1] ?? 0) < 1
            || ($imageInfo[0] ?? 0) > 4096
            || ($imageInfo[1] ?? 0) > 4096
            || (new finfo(FILEINFO_MIME_TYPE))->buffer($decoded) !== 'image/png'
        ) {
            echo json_encode(['success' => false, 'error' => 'Failed to decode image data.']);
            exit;
        }

        // Save into uploads/ai-cakes/
        $uploadDir = __DIR__ . '/uploads/ai-cakes';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }

        $timestamp = time();
        $order_number = generate_unique_ai_order_number($pdo);
        $filename = 'ai_' . $timestamp . '_' . $order_number . '.png';
        $filepath = $uploadDir . '/' . $filename;

        $written = file_put_contents($filepath, $decoded);
        if ($written === false) {
            echo json_encode(['success' => false, 'error' => 'Failed to save generated image on server.']);
            exit;
        }

        // IMPORTANT: store only the filename in the DB (as you requested)
        $dbPictureValue = $filename;

        // Insert DB row with picture column
        try {
            $stmt = $pdo->prepare("\n                INSERT INTO ai_cake_orders (\n                    order_number, customer_name, user_id, is_ai, picture, personalize, created_at, updated_at\n                ) VALUES (\n                    :order_number, :customer_name, :user_id, 1, :picture, :personalize, NOW(), NOW()\n                )\n            ");
            $stmt->execute([
                ':order_number' => $order_number,
                ':customer_name' => $customer_name,
                ':user_id' => user_id(),
                ':picture' => $dbPictureValue,
                ':personalize' => $prompt
            ]);
            $orderId = (int)$pdo->lastInsertId();
            if(!empty($_POST['favorite'])){$fav=$pdo->prepare("INSERT INTO favorites(user_id,favorite_type,ai_image,ai_prompt,description) VALUES(?,'ai_design',?,?,?)");$fav->execute([user_id(),$dbPictureValue,$prompt,'Saved from AI generator']);}
        } catch (Exception $e) {
            @unlink($filepath);
            error_log('AI design save failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Unable to save the design. Please try again.']);
            exit;
        }

        // return saved info so frontend can redirect to chat
        echo json_encode([
            'success' => true,
            'order_id' => $orderId,
            'order_number' => $order_number,
            // return the stored DB value (filename only)
            'picture' => $dbPictureValue
        ]);
        exit;
    }

    // generate flow - do NOT save to disk or DB here, only return base64 to client
    if ($action === 'generate') {
        $last=(int)($_SESSION['last_ai_generation_at'] ?? 0); if(time()-$last<20){http_response_code(429);echo json_encode(['success'=>false,'error'=>'Please wait before generating another design.']);exit;} $_SESSION['last_ai_generation_at']=time();
        if (empty($STABILITY_API_KEY)) {
            echo json_encode(['success' => false, 'error' => 'Server AI key not configured.']);
            exit;
        }

        $prompt = trim((string)($_POST['prompt'] ?? ''));
        $customer_name = current_user()['name'];

        if ($prompt === '' || mb_strlen($prompt) > 1000) {
            echo json_encode(['success' => false, 'error' => 'Enter a prompt of no more than 1,000 characters.']);
            exit;
        }
        if ($customer_name === '') {
            echo json_encode(['success' => false, 'error' => 'Customer name is required.']);
            exit;
        }

        // Build request payload for Stability (text-to-image)
        $payload = [
            'text_prompts' => [
                ['text' => $prompt]
            ],
            'cfg_scale' => 7,
            'clip_guidance_preset' => 'NONE',
            'height' => 1024,
            'width' => 1024,
            'samples' => 1,
            'steps' => 30
        ];

        $url = rtrim($apiHost, '/') . "/v1/generation/{$engineId}/text-to-image";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        $jsonPayload = json_encode($payload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $STABILITY_API_KEY,
            'User-Agent: CakeShop-AI/1.0'
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $err) {
            error_log('Stability request failed: ' . ($err ?: 'no response'));
            echo json_encode(['success' => false, 'error' => 'The design service is temporarily unavailable.']);
            exit;
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            error_log('Stability returned invalid JSON with HTTP ' . $code);
            echo json_encode(['success' => false, 'error' => 'The design service returned an invalid response.']);
            exit;
        }

        if ($code < 200 || $code >= 300) {
            error_log('Stability returned HTTP ' . $code);
            echo json_encode(['success' => false, 'error' => 'The design could not be generated. Please adjust the description and try again.']);
            exit;
        }

        if (!empty($data['artifacts'][0]['base64'])) {
            $b64 = $data['artifacts'][0]['base64'];

            // Return base64 to client (do not save)
            echo json_encode([
                'success' => true,
                'b64' => $b64,
                'prompt' => $prompt,
                'customer_name' => $customer_name
            ]);
            exit;
        }

        if (!empty($data['error'])) {
            error_log('Stability API error: ' . (is_scalar($data['error']) ? (string) $data['error'] : json_encode($data['error'])));
            echo json_encode(['success' => false, 'error' => 'The design could not be generated. Please adjust the description and try again.']);
            exit;
        }

        echo json_encode(['success' => false, 'error' => 'Unexpected AI service response.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action.']);
    exit;
}

// GET page (static heading text only)
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Cake Shop - Create</title>
  
   <!-- Logo Tab / Favicon -->
    <link rel="icon" type="image/png" href="/uploads/logo/logotab.png">
    <link rel="shortcut icon" type="image/png" href="/uploads/logo/logotab.png">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --theme-purple:#7b2cbf;
      --muted: #6b6b6b;
    }
    body{ background:#faf8ff; font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial; color:#222; }
    .ai-panel{ max-width:980px; margin:18px auto; background:#fff; border-radius:12px; padding:18px; box-shadow:0 12px 36px rgba(123,44,191,.06);}    
    .ai-title-text{ font-weight:900; color:var(--theme-purple); font-size:1.18rem; }
    .ai-sub{ color:var(--muted); font-size:.92rem; }
    .ai-result-card{ border-radius:10px; padding:12px; border:1px solid rgba(0,0,0,.04); text-align:center; background:linear-gradient(#fff,#fbfbff); }

    /* Violet-themed header buttons */
    .theme-btn-outline {
      color: var(--theme-purple);
      background: transparent;
      border: 1px solid rgba(123,44,191,0.12);
      transition: all .15s ease;
    }
    .theme-btn-outline:hover, .theme-btn-outline:focus {
      background: rgba(123,44,191,0.06);
      border-color: rgba(123,44,191,0.22);
      color: var(--theme-purple);
      box-shadow: 0 6px 18px rgba(123,44,191,0.06);
    }

    .theme-btn-filled {
      color: #fff;
      background: var(--theme-purple);
      border: 1px solid var(--theme-purple);
      transition: all .12s ease;
    }
    .theme-btn-filled:hover, .theme-btn-filled:focus {
      filter: brightness(.95);
      transform: translateY(-1px);
      box-shadow: 0 10px 24px rgba(123,44,191,0.14);
    }

    /* small icon adjustments */
    .site-header .btn { padding: .32rem .45rem; border-radius: .45rem; }
  </style>
</head>
<body>
  <header class="site-header position-sticky top-0 bg-white shadow-sm">
    <div class="container-fluid py-2 d-flex align-items-center justify-content-between">
      <a href="index.php" class="d-flex align-items-center text-decoration-none" aria-label="Home">
        <img src="logo.png" alt="Cake Shop" style="height:32px; object-fit:contain;">
      </a>
      <div class="me-2">
        <a class="btn btn-sm theme-btn-outline" href="view-all.php" aria-label="Menu">Menu</a>
      </div>
    </div>
  </header>

  <main>
    <div class="ai-panel">
      <!-- TEXT ONLY (not using PHP title variable) -->
      <div class="mb-3">
        <div class="ai-title-text">Customize Your Cake Now</div>
        <div class="ai-sub">Image will be saved only when you choose "Chat to owner".</div>
      </div>

      <form id="aiForm" class="ai-form" onsubmit="return onAICreate(event)">
        <div class="row g-2 mb-3">
          <div class="col-12 col-md-5">
            <label for="customer_name" class="form-label">Customer name</label>
            <input id="customer_name" name="customer_name" class="form-control" type="text" value="<?=e(current_user()['name'])?>" aria-label="Customer name" readonly>
          </div>
          <div class="col-12 col-md-7">
            <label for="prompt" class="form-label">Describe the cake (size, style, colors, decorations)</label>
            <textarea id="prompt" name="prompt" class="form-control" placeholder='e.g. "Three-tier vanilla cake with pink ombré buttercream, fresh peonies and gold leaf"' aria-label="AI cake description" maxlength="1000" required><?= e((string)($_GET['prompt'] ?? '')) ?></textarea>
          </div>
        </div>

        <div class="d-flex align-items-center mb-3">
          <button id="aiGenerateBtn" class="btn btn-primary" type="submit">Generate</button>
            &nbsp;<a href="my_orders.php" class="btn btn-primary">My orders</a>            

          <div id="aiStatus" class="small text-muted ms-2" aria-live="polite"></div>
        </div>
      </form>

      <div id="aiResults" aria-live="polite">
        <div class="ai-result-card" id="aiPlaceholder">
          <div class="text-muted small">No image yet - enter a description and click Generate.</div>
        </div>
      </div>
    </div>
  </main>

  <footer class="text-center py-3 mt-4 bg-white">
    <div class="container"><div class="small text-muted">© 2025 Cake Shop</div></div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // We store the last generated base64 in memory until the user chooses to save/chat.
    let lastGeneratedB64 = '';

    async function onAICreate(e){
      e.preventDefault();
      const btn = document.getElementById('aiGenerateBtn');
      const status = document.getElementById('aiStatus');
      const promptEl = document.getElementById('prompt');
      const customerEl = document.getElementById('customer_name');
      const prompt = promptEl.value.trim();
      const customerName = customerEl.value.trim();
      const results = document.getElementById('aiResults');

      if(!customerName){ alert('Please enter the customer name.'); customerEl.focus(); return false; }
      if(!prompt){ alert('Please enter a description for the cake.'); promptEl.focus(); return false; }

      btn.disabled = true; btn.textContent = 'Generating…'; status.textContent = 'This might take a few seconds.';

      try {
        const form = new FormData();
        form.append('prompt', prompt);
        form.append('customer_name', customerName);
        form.append('ajax', '1');
        form.append('action', 'generate');
        form.append('csrf_token', '<?= e(csrf_token()) ?>');

        const resp = await fetch(window.location.href, {
          method: 'POST',
          body: form,
          credentials: 'same-origin',
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await resp.json();
        if(!data || !data.success){
          const err = (data && data.error) ? data.error : 'Unknown error from server.';
          status.textContent = ''; alert('AI generation failed: ' + err);
          btn.disabled = false; btn.textContent = 'Generate'; return false;
        }

        const b64 = data.b64;
        if(!b64){
          status.textContent = ''; alert('AI service returned no image data.');
          btn.disabled = false; btn.textContent = 'Generate'; return false;
        }

        // Keep the base64 in memory until saved
        lastGeneratedB64 = b64;

        // Display the image as data URL
        const dataUrl = 'data:image/png;base64,' + b64;

        results.innerHTML = `
          <div class="ai-result-card" id="resultCard_preview">
            <div class="mb-2 text-start">
              <div class="small text-muted">Prompt: ${escapeHtml(prompt)}</div>
              <div style="margin-top:6px;"><strong>Customer:</strong> ${escapeHtml(customerName)}</div>
              <div><strong>Note:</strong> The image is not saved yet. Click "Chat to owner" to save and start a chat.</div>
            </div>

            <img id="previewImg" src="${dataUrl}" alt="AI generated cake" style="max-width:100%; border-radius:8px; display:block; margin:0 auto 10px;" />

            <div class="d-flex justify-content-center gap-2">
              <a id="downloadPreview" class="btn btn-sm btn-outline-primary" href="${dataUrl}" download="ai_preview.png" target="_blank" rel="noopener">Download</a>
              <button id="chatBtn_preview" class="btn btn-sm btn-primary">Chat to owner</button><label class="form-check-label small"><input id="favoriteDesign" type="checkbox" class="form-check-input"> Save to favorites</label>
            </div>

            <div id="chatMsg_preview" class="mt-2 small text-muted text-center"></div>
          </div>
        `;

        // attach chat button handler: save -> insert -> redirect to chat.php
        const chatBtn = document.getElementById('chatBtn_preview');
        if (chatBtn) {
          chatBtn.addEventListener('click', async function(){
            const ok = confirm('Do you want to save this design and chat with the owner?.');
            if(!ok) return;
            chatBtn.disabled = true; chatBtn.textContent = 'Saving…';
            document.getElementById('chatMsg_preview').textContent = '';

            try {
              const f = new FormData();
              f.append('ajax','1');
              f.append('action','save_and_chat');
              f.append('csrf_token', '<?= e(csrf_token()) ?>');
              f.append('image_b64', lastGeneratedB64);
              f.append('prompt', prompt);
              f.append('customer_name', customerName);
              if(document.getElementById('favoriteDesign')?.checked) f.append('favorite','1');

              const r = await fetch(window.location.href, { method:'POST', body: f, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'} });
              const res = await r.json();
              if (res && res.success) {
                document.getElementById('chatMsg_preview').textContent = 'Saved ✓';
                chatBtn.textContent = 'Saved';
                chatBtn.classList.remove('btn-primary'); chatBtn.classList.add('btn-secondary');
                // short delay so user sees "Saved" then redirect to chat page with order id
                setTimeout(()=>{ window.location.href = 'chat.php?order_id=' + encodeURIComponent(res.order_id); }, 600);
              } else {
                const er = res && res.error ? res.error : 'Failed to save';
                document.getElementById('chatMsg_preview').textContent = er;
                chatBtn.disabled = false; chatBtn.textContent = 'Chat to owner';
                alert('Error: ' + er);
              }
            } catch (err) {
              console.error(err);
              document.getElementById('chatMsg_preview').textContent = 'Error';
              chatBtn.disabled = false; chatBtn.textContent = 'Chat to owner';
              alert('Request error: ' + (err && err.message ? err.message : err));
            }
          });
        }

        status.textContent = 'Done';
      } catch (err) {
        console.error(err);
        alert('Request error: ' + (err && err.message ? err.message : err));
      } finally {
        btn.disabled = false; btn.textContent = 'Generate';
        setTimeout(()=>{ document.getElementById('aiStatus').textContent = ''; }, 2500);
      }
      return false;
    }

    function escapeHtml(s){ return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]; }); }
  </script>
</body>
</html>

