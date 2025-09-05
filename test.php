
<?php
// nap.php
// PHP backend endpoint (AJAX) + page frontend
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] == '1') {
    header('Content-Type: application/json; charset=utf-8');
    $partner_id  = '28804236835';
    $partner_key = '15901f5272e5bb948f18b0dd7a263247';
    $telco  = isset($_POST['telco']) ? trim($_POST['telco']) : '';
    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
    $serial = isset($_POST['serial']) ? trim($_POST['serial']) : '';
    $code   = isset($_POST['code']) ? trim($_POST['code']) : '';
    if ($telco === '' || $amount <= 0 || $serial === '' || $code === '') {
        echo json_encode(['ok'=>false,'message'=>'Thiếu dữ liệu (telco/amount/serial/code).']); exit;
    }
    $telco = strtoupper($telco);
    $payload = [
        'request_id' => rand(100000000, 999999999),
        'code'       => $code,
        'partner_id' => $partner_id,
        'serial'     => $serial,
        'telco'      => $telco,
        'amount'     => $amount,
        'command'    => 'charging',
        'sign'       => md5($partner_key . $code . $serial)
    ];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://doithe1s.vn/chargingws/v2');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $resp = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);
    if ($resp === false || $resp === null) {
        echo json_encode(['ok'=>false,'message'=>'Không kết nối được tới API: '.$curlErr]); exit;
    }
    $obj = json_decode($resp);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['ok'=>false,'message'=>'Phản hồi API không hợp lệ','raw'=>$resp]); exit;
    }
    echo json_encode(['ok'=>true,'provider'=>$obj]);
    exit;
}
?>










<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Nạp thẻ cào | RobuxVEX</title>

  <!-- Fonts & UI -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <!-- Firebase compat SDK (giữ như bản gốc) -->
  <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
  <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-firestore-compat.js"></script>
  <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>

  <style>
    body { font-family: 'Inter', sans-serif; background:#f4f6fb; }

    .btn-gradient{background:linear-gradient(90deg,#8e2de2,#4facfe);transition:.25s}
    .btn-gradient:hover{filter:brightness(1.1)}
    .card{background:#fff;border-radius:20px;box-shadow:0 8px 20px rgba(0,0,0,.08);padding:24px}
    input,select{border-radius:12px!important}

    /* Hamburger (giống muahang) */
    .menu-btn{font-size:22px;cursor:pointer;padding:10px;color:#374151}
    .menu-btn.hidden{display:none}
    .sidebar{
      height:100%;width:280px;position:fixed;top:0;left:-280px;background:#0a2a5c;
      transition:left .35s ease;padding:16px 0;color:#fff;z-index:1000;box-shadow:0 8px 24px rgba(0,0,0,.3)
    }
    .sidebar.open{left:0}
    .sidebar a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#fff;text-decoration:none}
    .sidebar a:hover{background:rgba(255,255,255,.1);border-radius:10px}
    .sidebar h3{margin:16px 0 8px 20px;font-size:12px;opacity:.8;text-transform:uppercase;letter-spacing:.06em}

    /* User box trong sidebar (tên + số dư) */
    .user-box{display:flex;align-items:center;gap:12px;padding:0 20px 14px 20px;margin-bottom:6px;border-bottom:1px dashed rgba(255,255,255,.2)}
    .user-avatar{width:42px;height:42px;border-radius:999px;background:#123b7d;display:grid;place-items:center;font-weight:800}
    .balance-chip{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:800;
      background:linear-gradient(90deg,#22c55e,#166534);color:#fff}

    /* Loading overlay */
    #loadingOverlay{
      position:fixed;inset:0;background:rgba(255,255,255,.85);display:none;
      z-index:2000;align-items:center;justify-content:center;flex-direction:column;
      font-weight:600;font-size:18px;color:#333
    }
    .spinner{border:6px solid #f3f3f3;border-top:6px solid #4facfe;border-radius:50%;
      width:56px;height:56px;animation:spin 1s linear infinite;margin-bottom:12px}
    @keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}

    /* Success modal */
    #successModal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:center;justify-content:center;z-index:3000}
    #successModal .modal-box{
      background:#fff;border-radius:16px;padding:24px;text-align:center;max-width:320px;width:90%;
      animation:scaleIn .28s ease;box-shadow:0 8px 20px rgba(0,0,0,.2)
    }
    #successModal h3{font-size:18px;font-weight:700;margin-bottom:14px}
    #successModal button{
      background:linear-gradient(90deg,#8e2de2,#4facfe);color:#fff;border:0;padding:10px 24px;border-radius:999px;font-weight:700;cursor:pointer
    }
    @keyframes scaleIn{0%{transform:scale(.7);opacity:0}100%{transform:scale(1);opacity:1}}
    .fade-out{animation:fadeOut .4s forwards ease}
    @keyframes fadeOut{to{opacity:0;transform:scale(.9)}}

    /* Inline error */
    .err{color:#ef4444;font-size:12px;margin-top:6px}
    .muted{opacity:.6;pointer-events:none}

    /* Marker trước tiêu đề (giống lịch sử mua ở muahang) */
    .section-title{position:relative;padding-left:12px;font-weight:800}
    .section-title::before{
      content:"";position:absolute;left:0;top:0.2em;bottom:0.2em;width:6px;border-radius:6px;
      background:linear-gradient(180deg,#7c3aed,#1e3a8a)
    }

    /* Chips màu theo yêu cầu */
    .chip{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:800;color:#fff;white-space:nowrap}
    .chip-nhamang{background:linear-gradient(90deg,#7c3aed,#1e3a8a)}
    .chip-thucnhan{background:linear-gradient(90deg,#991b1b,#facc15)}
    .st-pending{background:linear-gradient(90deg,#f97316,#facc15)}
    .st-completed{background:linear-gradient(90deg,#4ade80,#15803d)}
    .st-failed{background:linear-gradient(90deg,#991b1b,#1e3a8a)}

    /* Bảng */
    .table-head th{background:#000;color:#fff;border-right:1px solid #fff}
    .table-head th:last-child{border-right:0}
  </style>
</head>
<body>

 <!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Menu Robuxvex</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <style>
    :root{
      --grad-from: #facc15; /* vàng */
      --grad-to:   #2b6cff; /* xanh dương */
    }
    body { font-family: 'Inter', sans-serif; margin:0; }

    /* Hamburger + Sidebar */
    .menu-btn{font-size:22px;cursor:pointer;padding:10px;color:#374151}
    .menu-btn.hidden{display:none}
    .sidebar{
      height:100%;width:280px;position:fixed;top:0;left:-280px;background:#0a2a5c;
      transition:left .35s ease;padding:16px 0;color:#fff;z-index:1000;box-shadow:0 8px 24px rgba(0,0,0,.3)
    }
    .sidebar.open{left:0}
    .sidebar a{display:flex;align-items:center;gap:12px;padding:12px 20px;color:#fff;text-decoration:none}
    .sidebar a:hover{background:rgba(255,255,255,.08);border-radius:10px}
    .sidebar h3{margin:16px 0 8px 20px;font-size:12px;opacity:.85;text-transform:uppercase;letter-spacing:.06em}

    /* User box */
    .user-box{display:flex;align-items:center;gap:12px;padding:0 20px 14px 20px;margin-bottom:6px;border-bottom:1px dashed rgba(255,255,255,.12)}
    .user-avatar{width:42px;height:42px;border-radius:999px;background:#123b7d;display:grid;place-items:center;font-weight:800}
    .balance-chip{display:inline-block;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:800;
      background:linear-gradient(90deg,#22c55e,#166534);color:#fff}

    /* Header */
    .site-header{background:#fff;box-shadow:0 6px 18px rgba(16,24,40,0.06);padding:10px 16px;position:sticky;top:0;z-index:60}
    .header-inner{max-width:1100px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:12px}

    /* Brand box: outer gradient (khung nhỏ) + inner white pill with black bold text */
    .brand-box{
      display:inline-block;
      padding:2px;               /* độ dày khung */
      border-radius:10px;
      background: linear-gradient(90deg, var(--grad-from), var(--grad-to));
      box-shadow: 0 3px 10px rgba(43,108,255,0.12);
    }
    .brand-box .brand-inner{
      display:inline-block;
      background:#ffffff;
      padding:6px 10px;
      border-radius:8px;
      font-weight:900;
      color:#000;
      font-size:1rem;
      letter-spacing:0.8px;
    }

    /* small logo image */
    .site-logo{height:36px;display:block}

    /* small responsive tweaks */
    @media (max-width:640px){
      .brand-box .brand-inner{font-size:0.95rem;padding:5px 8px}
    }
  </style>
</head>
<body>

  <!-- Sidebar -->
  <div id="sidebar" class="sidebar" aria-hidden="true">
    <div class="user-box" id="userBox">
      <div class="user-avatar" id="userAvatar">R</div>
      <div>
        <div id="userName" class="font-semibold">Khách</div>
        <div id="userBalance" class="balance-chip">Số dư: 0đ</div>
      </div>
    </div>

    <h3>ĐIỀU HƯỚNG</h3>
    <a href="index.html"><i class="fas fa-home"></i> Trang chủ</a>
    <a href="muahang.html"><i class="fas fa-coins"></i> Mua robux</a>
    <a href="nap.html"><i class="fas fa-wallet"></i> Nạp tiền</a>
    <a href="muahang.html#history"><i class="fas fa-history"></i> Lịch sử mua hàng</a>

    <h3>HỖ TRỢ</h3>
    <a href="https://zalo.me/0907486634"><i class="fab fa-facebook-messenger"></i> Zalo</a>
    <a href="mailto:vochuoi86@gmail.com"><i class="fas fa-envelope"></i> Gửi mail</a>
  </div>

  <!-- Header -->
  <header class="site-header" role="banner">
    <div class="header-inner">
      <div class="left-group" style="display:flex;align-items:center;gap:12px">
        <button class="menu-btn" id="menuBtn" aria-label="Mở menu" onclick="toggleMenu()"><i class="fas fa-bars"></i></button>

        <!-- logo image -->
        <img src="https://i.postimg.cc/8P0gT0YD/file-0000000025b061f780c5334d2cfcad87.png" alt="logo" class="site-logo">

        <!-- brand box ngay cạnh logo -->
        <div class="brand-box" aria-hidden="true">
          <span class="brand-inner">ROBUXVEX</span>
        </div>
      </div>

      <!-- right side (giữ trống / có thể để nút) -->
      <div class="right-group" aria-hidden="true">
        <!-- bạn có thể thêm user action ở đây nếu cần -->
      </div>
    </div>
  </header>

<script><
  const sidebar = document.getElementById("sidebar");
  const menuBtn = document.getElementById("menuBtn");
  function toggleMenu(){
    sidebar.classList.toggle('open');
    // set aria for accessibility
    const open = sidebar.classList.contains('open');
    sidebar.setAttribute('aria-hidden', !open);
    menuBtn.classList.toggle('hidden', open);
  }
  document.addEventListener('click', (e)=>{
    if(sidebar.classList.contains('open') && !sidebar.contains(e.target) && !menuBtn.contains(e.target)){
      sidebar.classList.remove('open');
      sidebar.setAttribute('aria-hidden','true');
      menuBtn.classList.remove('hidden');
    }
  });
</script>



</div>
  <!-- Breadcrumb -->
  <div class="max-w-6xl mx-auto mt-4 px-4 text-sm text-gray-500">Trang chủ → Nạp thẻ cào</div>

  <!-- Main -->
  <div class="max-w-6xl mx-auto mt-4 px-4 grid grid-cols-1 md:grid-cols-2 gap-8">
    <!-- Form -->
    <div class="card">
      <h2 class="text-xl font-semibold mb-6 text-gray-800">🔐 Nạp thẻ cào</h2>
      <form id="cardForm" class="space-y-5" novalidate>
        <div>
          <label class="block mb-2 font-medium">Loại thẻ</label>
          <select id="cardType" class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-indigo-500">
            <option value="Viettel">Viettel (20%)</option>
            <option value="Mobifone">Mobifone (20%)</option>
            <option value="Vinaphone">Vinaphone (20%)</option>
            <option value="Vietnamobile">Vietnamobile (20%)</option>
            <option value="Zing">Zing (20%)</option>
            <option value="Vcoin">Vcoin (20%)</option>
            <option value="Garena">Garena (20%)</option>
            <option value="Gate">Gate (20%)</option>
          </select>
        </div>

        <div>
          <label class="block mb-2 font-medium">Mệnh giá</label>
          <select id="amount" class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-indigo-500">
            <option value="10000">10.000đ</option>
            <option value="20000">20.000đ</option>
            <option value="50000">50.000đ</option>
            <option value="100000">100.000đ</option>
            <option value="200000">200.000đ</option>
            <option value="500000">500.000đ</option>
          </select>
        </div>

        <div>
          <label class="block mb-2 font-medium">Số serial</label>
          <input id="serial" type="text" class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-indigo-500">
          <div id="serialError" class="err"></div>
        </div>

        <div>
          <label class="block mb-2 font-medium">Mã thẻ</label>
          <input id="pin" type="text" class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-indigo-500">
          <div id="pinError" class="err"></div>
        </div>

        <div class="text-center mt-2">
          <!-- Giữ ký hiệu ₫ ở ngoài span như bản gốc + có hiệu ứng đếm -->
          <div class="text-2xl font-bold text-green-600 mt-1">₫<span id="thucnhan">0</span></div>
        </div>

        <button id="submitBtn" type="submit" class="w-full btn-gradient text-white py-3 rounded-full shadow-lg text-lg font-semibold">
          Nạp thẻ ngay
        </button>

        <div id="authInlineMsg" class="err text-center"></div>
      </form>
    </div>

    <!-- Hướng dẫn -->
    <div class="card">
      <h2 class="text-xl font-semibold mb-6 text-gray-800">📘 Hướng dẫn nạp tiền</h2>
      <ol class="list-decimal list-inside space-y-3 text-gray-700">
        <li>Chọn loại thẻ, nhập serial, mã thẻ, mệnh giá → bấm nạp.</li>
        <li>Hệ thống xử lý (0-5h), tiền cộng vào số dư nếu hợp lệ.</li>
      </ol>
      <div class="mt-6 text-sm text-yellow-800 bg-yellow-100 border border-yellow-300 rounded-lg p-4">
        <strong>Lưu ý:</strong><br>- Phí nạp bằng thẻ cào là <strong>20%</strong>.<br>- Nếu nhập sai mệnh giá thẻ sẽ bị <em>trừ 50% phí</em>.
      </div>
    </div>
  </div>

  <!-- Lịch sử -->
  <div class="max-w-6xl mx-auto mt-8 px-4">
    <div class="card">
      <h2 class="section-title text-xl mb-4 text-gray-800">📊 Lịch sử nạp thẻ</h2>

      <div id="historyGuestNotice" class="text-sm text-yellow-900 bg-yellow-100 border border-yellow-300 rounded-lg p-4 hidden">
        Vui lòng đăng nhập để xem lịch sử nạp của bạn.
      </div>

      <div id="historyWrap" class="overflow-x-auto hidden">
        <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden">
          <thead class="table-head">
            <tr>
              <th class="px-4 py-3">Nhà mạng</th>
              <th class="px-4 py-3">Số serial</th>
              <th class="px-4 py-3">Mệnh giá</th>
              <th class="px-4 py-3">Thực nhận</th>
              <th class="px-4 py-3">Trạng thái</th>
              <th class="px-4 py-3">Ngày nạp</th>
            </tr>
          </thead>
          <tbody id="historyTable" class="divide-y divide-gray-200">
            <tr><td colspan="6" class="text-center py-5 text-gray-500">Chưa có giao dịch nào.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Loading & Success -->
  <div id="loadingOverlay"><div class="spinner"></div><div>Đang xử lý...</div></div>
  <div id="successModal">
    <div class="modal-box">
      <h3>🎉 Nạp thẻ thành công<br>Vui lòng chờ duyệt</h3>
      <button id="okBtn">OK</button>
    </div>
  </div>

  <!-- Script -->
<script>
    /* ===== MENU ===== */
    const sidebar = document.getElementById("sidebar");
    const menuBtn = document.querySelector(".menu-btn");
    window.toggleMenu = function () {
      sidebar.classList.toggle("open");
      menuBtn.classList.toggle("hidden");
    };
    document.addEventListener("click", (e) => {
      if (sidebar.classList.contains("open") && !sidebar.contains(e.target) && !menuBtn.contains(e.target)) {
        sidebar.classList.remove("open");
        menuBtn.classList.remove("hidden");
      }
    });

    /* ===== FIREBASE (giữ cấu hình gốc) ===== */
    const firebaseConfig = {
      apiKey:"AIzaSyBbTe4CZM8mUqsJUyFBIjcSc3w4rvIbmzc",
      authDomain:"robuxvex.firebaseapp.com",
      projectId:"robuxvex",
      storageBucket:"robuxvex.appspot.com",
      messagingSenderId:"291500837886",
      appId:"1:291500837886:web:08ae60ca6454c209328eaf"
    };
    firebase.initializeApp(firebaseConfig);
    const db = firebase.firestore();
    const auth = firebase.auth();

    /* ===== CONSTANTS ===== */
    const FEE_RATE = 0.8;
    const amountSelect = document.getElementById("amount");
    const thucnhanSpan = document.getElementById("thucnhan");
    const submitBtn = document.getElementById("submitBtn");
    const authInlineMsg = document.getElementById("authInlineMsg");
    const historyWrap = document.getElementById("historyWrap");
    const historyGuestNotice = document.getElementById("historyGuestNotice");

    /* ===== Hiệu ứng đếm tiền thực nhận ===== */
    function parseCurrentValue() {
      const n = thucnhanSpan.textContent.replace(/[^\d]/g,'');
      return parseInt(n || "0",10) || 0;
    }
    function animateThucNhan(toVal) {
      const fromVal = parseCurrentValue();
      const start = performance.now();
      const dur = 500;
      function step(now){
        const t = Math.min(1,(now-start)/dur);
        const val = Math.floor(fromVal + (toVal - fromVal)*t);
        thucnhanSpan.textContent = val.toLocaleString("vi-VN") + "đ";
        if(t<1) requestAnimationFrame(step);
      }
      requestAnimationFrame(step);
    }
    function updateThucNhan() {
      const val = parseInt(amountSelect.value || 0, 10) || 0;
      const target = Math.floor(val * FEE_RATE);
      animateThucNhan(target);
    }
    amountSelect.addEventListener("change", updateThucNhan);
    amountSelect.addEventListener("input", updateThucNhan);
    updateThucNhan(); // tính ngay khi mở trang

    /* ===== Quy tắc kiểm tra độ dài serial/pin (giữ gốc) ===== */
    const cardRules = {
      "Viettel":{serial:[10,16],pin:[10,16]},
      "Mobifone":{serial:[10,16],pin:[10,16]},
      "Vinaphone":{serial:[10,16],pin:[10,16]},
      "Vietnamobile":{serial:[10,16],pin:[10,16]},
      "Zing":{serial:[9,16],pin:[10,16]},
      "Vcoin":{serial:[9,16],pin:[10,16]},
      "Garena":{serial:[9,16],pin:[10,16]},
      "Gate":{serial:[9,16],pin:[10,16]}
    };

    const serialEl = document.getElementById("serial");
    const pinEl = document.getElementById("pin");
    const serialErr = document.getElementById("serialError");
    const pinErr = document.getElementById("pinError");
    const cardTypeEl = document.getElementById("cardType");

    function validateLength(el, errEl) {
      const rules = cardRules[cardTypeEl.value];
      const isSerial = el === serialEl;
      const [min, max] = isSerial ? rules.serial : rules.pin;
      const len = el.value.length;
      if (len === 0) { errEl.textContent = ""; return; }
      if (len < min || len > max) {
        errEl.textContent = `⚠️ ${isSerial ? "Serial" : "Mã thẻ"} phải từ ${min}-${max} ký tự`;
      } else {
        errEl.textContent = "";
      }
    }
    serialEl.addEventListener("blur", () => validateLength(serialEl, serialErr));
    pinEl.addEventListener("blur", () => validateLength(pinEl, pinErr));
    cardTypeEl.addEventListener("change", () => {
      validateLength(serialEl, serialErr);
      validateLength(pinEl, pinErr);
    });

    /* ===== Helper enable/disable form ===== */
    const formControls = [cardTypeEl, amountSelect, serialEl, pinEl, submitBtn];
    function setFormEnabled(enabled) {
      formControls.forEach(el => {
        el.disabled = !enabled;
        if (el.id === "submitBtn") {
          el.classList.toggle("muted", !enabled);
        }
      });
    }

    /* ===== User info trong sidebar (giống muahang) ===== */
    const userNameEl = document.getElementById("userName");
    const userBalanceEl = document.getElementById("userBalance");
    const userAvatarEl = document.getElementById("userAvatar");

    async function loadUserBox(uid, user){
      // tên: ưu tiên doc.users.name -> auth.displayName -> auth.email
      try{
        const docRef = db.collection("users").doc(uid);
        const snap = await docRef.get();
        const data = snap.exists ? snap.data() : {};
        const name = data?.name || user?.displayName || user?.email || "Người dùng";
        const bal = Number(data?.balance || 0);
        userNameEl.textContent = name;
        userBalanceEl.textContent = "Số dư: " + bal.toLocaleString("vi-VN") + "đ";
        // avatar: ký tự đầu
        const c = (name||"R").trim()[0]?.toUpperCase() || "R";
        userAvatarEl.textContent = c;
      }catch(e){
        userNameEl.textContent = user?.displayName || user?.email || "Người dùng";
        userBalanceEl.textContent = "Số dư: 0đ";
      }
    }

    /* ===== AUTH ===== */
    auth.onAuthStateChanged(async (user) => {
      if (!user) {
        setFormEnabled(false);
        authInlineMsg.innerHTML = 'Bạn cần <a href="dn.html" class="underline font-semibold">đăng nhập</a> để nạp thẻ.';
        historyWrap.classList.add("hidden");
        historyGuestNotice.classList.remove("hidden");
        userNameEl.textContent = "Khách";
        userBalanceEl.textContent = "Số dư: 0đ";
        userAvatarEl.textContent = "R";
      } else {
        setFormEnabled(true);
        authInlineMsg.textContent = "";
        historyGuestNotice.classList.add("hidden");
        historyWrap.classList.remove("hidden");
        loadHistory(user.uid);
        loadUserBox(user.uid, user);
      }
    });

    /* ===== Submit (giữ logic gốc + overlay, modal) ===== */
    /* ===== Submit ===== */
const cardForm = document.getElementById("cardForm");
cardForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  const user = auth.currentUser;
  if (!user) {
    authInlineMsg.innerHTML = 'Bạn cần <a href="dn.html" class="underline font-semibold">đăng nhập</a> để nạp thẻ.';
    return;
  }

  const pin = pinEl.value.trim();
  const serial = serialEl.value.trim();
  const cardType = cardTypeEl.value;
  const amount = parseInt(amountSelect.value || "0", 10) || 0;

  serialErr.textContent = "";
  pinErr.textContent = "";
  validateLength(serialEl, serialErr);
  validateLength(pinEl, pinErr);
  if (serialErr.textContent || pinErr.textContent) return;

  const loadingOverlay = document.getElementById("loadingOverlay");
  loadingOverlay.style.display = "flex";
  setFormEnabled(false);

  try {
    // gọi PHP backend gửi sang doithe1s
    const resp = await fetch("nap.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({
        ajax: "1",
        telco: cardType,
        amount,
        serial,
        code: pin
      })
    });
    const data = await resp.json();

    loadingOverlay.style.display = "none";
    setFormEnabled(true);

    if (!data.ok) {
      alert("Lỗi: " + data.message);
      return;
    }

    // lưu thêm vào Firestore (có trạng thái API trả về)
    await db.collection("napthe").add({
      uid: user.uid,
      cardType, amount, serial, pin,
      createdAt: firebase.firestore.FieldValue.serverTimestamp(),
      status: data.provider?.status || "pending",
      response: data.provider || {}
    });

    // hiện modal thành công
    const modal = document.getElementById("successModal");
    modal.style.display = "flex";
    document.getElementById("okBtn").onclick = () => {
      modal.querySelector(".modal-box").classList.add("fade-out");
      setTimeout(()=>{modal.style.display="none"; modal.querySelector(".modal-box").classList.remove("fade-out");},400);
    };
    cardForm.reset();
    animateThucNhan(0);

  } catch (err) {
    loadingOverlay.style.display = "none";
    setFormEnabled(true);
    alert("Có lỗi: " + err.message);
  }
});
    /* ===== Load history + chips màu ===== */
    function statusClass(st){
      const s = (st||"pending").toLowerCase();
      if(s==="completed" || s==="success") return "st-completed";
      if(s==="failed" || s==="error" || s==="rejected") return "st-failed";
      return "st-pending";
    }

    async function loadHistory(uid) {
      const tbody = document.getElementById("historyTable");
      const snap = await db.collection("napthe").where("uid","==",uid).orderBy("createdAt","desc").get();
      tbody.innerHTML = "";
      if (snap.empty) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-gray-500">Chưa có giao dịch nào.</td></tr>';
        return;
      }
      snap.forEach(doc=>{
        const d = doc.data();
        const row = document.createElement("tr");
        row.innerHTML = `
          <td class="px-4 py-2 border">
            <span class="chip chip-nhamang">${d.cardType}</span>
          </td>
          <td class="px-4 py-2 border">${d.serial}</td>
          <td class="px-4 py-2 border">${(d.amount||0).toLocaleString("vi-VN")}đ</td>
          <td class="px-4 py-2 border">
            <span class="chip chip-thucnhan">${Math.floor((d.amount||0)*FEE_RATE).toLocaleString("vi-VN")}đ</span>
          </td>
          <td class="px-4 py-2 border">
            <span class="chip ${statusClass(d.status)}">${(d.status||"pending")}</span>
          </td>
          <td class="px-4 py-2 border">${d.createdAt?.toDate().toLocaleString("vi-VN")||""}</td>`;
        tbody.appendChild(row);
      });
    }
  </script>
</body>
</html>
