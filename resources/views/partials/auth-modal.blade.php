{{-- ─────────────────────────────────────────────────────────────────────────
     Login / Register modal.

     Included site-wide for guests (see layouts/app.blade.php). Any guest action
     that requires an account — liking or commenting on a post — calls
     window.openAuthModal('login' | 'register') to pop this open. Sharing never
     opens it. On success the page reloads so the now-authenticated state paints.
     Uses the shared window.Tanbat.{openModal,closeModal,toast} helpers (app.js).
──────────────────────────────────────────────────────────────────────────── --}}
<div id="modalAuth" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
  <div class="am-card" role="dialog" aria-modal="true" aria-labelledby="amTitle">
    <button type="button" data-close-modal class="am-x" aria-label="Close">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>

    <div class="am-head">
      <div class="am-logo">T</div>
      <div class="am-eyebrow" id="amEyebrow">Welcome back</div>
      <div class="am-title" id="amTitle">Sign in to Tanbat</div>
      <div class="am-sub" id="amSub">Sign in to like, comment and join the conversation.</div>
    </div>

    <div class="am-tabs" id="amTabs">
      <span class="am-pill" id="amPill"></span>
      <button type="button" class="am-tab is-active" data-am-tab="login">Sign In</button>
      <button type="button" class="am-tab" data-am-tab="register">Register</button>
    </div>

    <div class="am-body">
      {{-- ── LOGIN ── --}}
      <form id="amLoginForm" class="am-pane show" novalidate>
        <div class="am-fg">
          <label class="am-lbl" for="amLId">Email or Username</label>
          <input class="am-inp" id="amLId" type="text" placeholder="you@example.com" autocomplete="username">
          <div class="am-err" id="amLIdE">Enter your email or username.</div>
        </div>
        <div class="am-fg">
          <label class="am-lbl" for="amLPw">Password</label>
          <div class="am-iw">
            <input class="am-inp" id="amLPw" type="password" placeholder="••••••••" autocomplete="current-password">
            <button type="button" class="am-eye" data-am-eye="amLPw" tabindex="-1" aria-label="Toggle password"></button>
          </div>
          <div class="am-err" id="amLPwE">Enter your password.</div>
        </div>
        <button type="submit" class="am-sub-btn" id="amBtnLogin">Sign In</button>
        <p class="am-switch">New to Tanbat? <button type="button" data-am-tab="register">Create a free account</button></p>
      </form>

      {{-- ── REGISTER ── --}}
      <form id="amRegForm" class="am-pane" novalidate>
        <div class="am-row">
          <div class="am-fg">
            <label class="am-lbl" for="amRName">Full name</label>
            <input class="am-inp" id="amRName" type="text" placeholder="Jane Smith" autocomplete="name">
            <div class="am-err" id="amRNameE">Name is required.</div>
          </div>
          <div class="am-fg am-fg-sm">
            <label class="am-lbl" for="amRAge">Age</label>
            <input class="am-inp" id="amRAge" type="number" inputmode="numeric" placeholder="25" min="13" max="120">
            <div class="am-err" id="amRAgeE">13+ required.</div>
          </div>
        </div>
        <div class="am-row">
          <div class="am-fg">
            <label class="am-lbl" for="amRGender">Gender</label>
            <select class="am-inp" id="amRGender">
              <option value="">Select</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="non-binary">Non-binary</option>
              <option value="other">Other</option>
              <option value="prefer_not">Prefer not to say</option>
            </select>
            <div class="am-err" id="amRGenderE">Please select a gender.</div>
          </div>
          <div class="am-fg">
            <label class="am-lbl" for="amRCountry">Country</label>
            <select class="am-inp" id="amRCountry"><option value="">🌍 Select country</option></select>
            <div class="am-err" id="amRCountryE">Country is required.</div>
          </div>
        </div>
        <div class="am-row">
          <div class="am-fg">
            <label class="am-lbl" for="amREmail">Email</label>
            <input class="am-inp" id="amREmail" type="email" inputmode="email" placeholder="you@example.com" autocomplete="email">
            <div class="am-err" id="amREmailE">Valid email required.</div>
          </div>
          <div class="am-fg">
            <label class="am-lbl" for="amRUser">Username</label>
            <input class="am-inp" id="amRUser" type="text" placeholder="jane_smith" autocomplete="username">
            <div class="am-err" id="amRUserE">Username is required.</div>
          </div>
        </div>
        <div class="am-fg">
          <label class="am-lbl" for="amRPw">Password</label>
          <div class="am-iw">
            <input class="am-inp" id="amRPw" type="password" placeholder="Min. 8 characters" autocomplete="new-password">
            <button type="button" class="am-eye" data-am-eye="amRPw" tabindex="-1" aria-label="Toggle password"></button>
          </div>
          <div class="am-err" id="amRPwE">Minimum 8 characters required.</div>
        </div>
        <div class="am-fg">
          <label class="am-lbl">Profile picture</label>
          <div class="am-av">
            <div class="am-av-ring" id="amAvRing">
              <div class="am-av-ph" id="amAvPh">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </div>
              <img id="amAvImg" src="" alt="">
            </div>
            <div class="am-av-info">
              <div class="am-av-lbl">Upload your photo</div>
              <div class="am-av-hint">PNG, JPG or WEBP · Max 5 MB</div>
              <button type="button" class="am-upl" id="amAvBtn">Choose file</button>
            </div>
          </div>
          <div class="am-err" id="amRAvE">Profile picture is required.</div>
          <input type="file" id="amPicIn" accept="image/*" hidden>
        </div>
        <button type="submit" class="am-sub-btn" id="amBtnReg">Create account</button>
        <p class="am-tos">By joining you agree to our Terms and <a href="{{ url('/privacy') }}">Privacy</a>.</p>
        <p class="am-switch">Already have an account? <button type="button" data-am-tab="login">Sign in</button></p>
      </form>
    </div>
  </div>
</div>

<style>
  #modalAuth .am-card{
    width:100%; max-width:440px; max-height:calc(100vh - 32px);
    display:flex; flex-direction:column; overflow:hidden;
    background:#fff; border-radius:22px;
    box-shadow:0 30px 80px rgba(15,23,42,.35);
    position:relative; animation:amPop .22s ease;
  }
  @keyframes amPop{ from{ opacity:0; transform:translateY(14px) scale(.97);} to{ opacity:1; transform:none;} }
  #modalAuth .am-x{
    position:absolute; top:14px; right:14px; z-index:2;
    height:34px; width:34px; display:grid; place-items:center;
    border-radius:9999px; color:#64748B; background:rgba(241,245,249,.8);
    transition:.15s;
  }
  #modalAuth .am-x:hover{ background:#E2E8F0; color:#1E1B4B; }
  #modalAuth .am-x svg{ height:18px; width:18px; }

  #modalAuth .am-head{ padding:26px 26px 0; text-align:center; }
  #modalAuth .am-logo{
    height:46px; width:46px; margin:0 auto 12px; border-radius:14px;
    display:grid; place-items:center;
    background:linear-gradient(135deg,#6C63FF,#FF6584); color:#fff;
    font-weight:800; font-size:22px; box-shadow:0 8px 22px rgba(108,99,255,.35);
  }
  #modalAuth .am-eyebrow{ font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:#6C63FF; }
  #modalAuth .am-title{ font-size:20px; font-weight:800; color:#1E1B4B; margin-top:3px; }
  #modalAuth .am-sub{ font-size:13px; color:#64748B; margin-top:5px; }

  #modalAuth .am-tabs{
    position:relative; display:grid; grid-template-columns:1fr 1fr; gap:4px;
    margin:18px 26px 0; padding:4px; border-radius:12px; background:#F1F5F9;
  }
  #modalAuth .am-pill{
    position:absolute; top:4px; left:4px; bottom:4px; width:calc(50% - 4px);
    border-radius:9px; background:#fff; box-shadow:0 1px 3px rgba(20,20,50,.12);
    transition:transform .22s cubic-bezier(.4,0,.2,1);
  }
  #modalAuth .am-tabs.is-reg .am-pill{ transform:translateX(100%); }
  #modalAuth .am-tab{
    position:relative; z-index:1; padding:8px 0; border-radius:9px;
    font-size:13px; font-weight:700; color:#64748B; transition:color .2s;
  }
  #modalAuth .am-tab.is-active{ color:#1E1B4B; }

  #modalAuth .am-body{ padding:18px 26px 26px; overflow-y:auto; }
  #modalAuth .am-pane{ display:none; }
  #modalAuth .am-pane.show{ display:block; }

  #modalAuth .am-row{ display:grid; grid-template-columns:1fr 1fr; gap:12px; }
  #modalAuth .am-row .am-fg-sm{ max-width:96px; }
  #modalAuth .am-fg{ margin-bottom:13px; }
  #modalAuth .am-lbl{ display:block; font-size:12px; font-weight:600; color:#475569; margin-bottom:5px; }
  #modalAuth .am-inp{
    width:100%; border:1.5px solid #E2E8F0; border-radius:11px;
    padding:11px 12px; font-size:14px; color:#0F172A; background:#F8FAFC;
    transition:border-color .15s, background .15s, box-shadow .15s;
  }
  #modalAuth select.am-inp{ appearance:none; -webkit-appearance:none; cursor:pointer; }
  #modalAuth .am-inp:focus{ outline:none; border-color:#6C63FF; background:#fff; box-shadow:0 0 0 4px rgba(108,99,255,.12); }
  #modalAuth .am-inp.err{ border-color:#EF4444; background:#FEF2F2; }
  #modalAuth .am-iw{ position:relative; }
  #modalAuth .am-iw .am-inp{ padding-right:42px; }
  #modalAuth .am-eye{
    position:absolute; right:6px; top:50%; transform:translateY(-50%);
    height:32px; width:32px; display:grid; place-items:center;
    border-radius:8px; color:#94A3B8;
  }
  #modalAuth .am-eye:hover{ color:#475569; background:#F1F5F9; }
  #modalAuth .am-eye svg{ height:17px; width:17px; }
  #modalAuth .am-err{ display:none; font-size:11.5px; color:#EF4444; margin-top:5px; }
  #modalAuth .am-err.on{ display:block; }

  #modalAuth .am-av{ display:flex; align-items:center; gap:14px; }
  #modalAuth .am-av-ring{
    height:64px; width:64px; border-radius:9999px; flex-shrink:0;
    border:2px dashed #CBD5E1; overflow:hidden; cursor:pointer;
    display:grid; place-items:center; position:relative; background:#F8FAFC;
    transition:border-color .15s;
  }
  #modalAuth .am-av-ring:hover{ border-color:#6C63FF; }
  #modalAuth .am-av-ring.err{ border-color:#EF4444; background:#FEF2F2; }
  #modalAuth .am-av-ph{ color:#94A3B8; }
  #modalAuth .am-av-ph svg{ height:26px; width:26px; }
  #modalAuth .am-av-ring img{ position:absolute; inset:0; height:100%; width:100%; object-fit:cover; display:none; }
  #modalAuth .am-av-lbl{ font-size:13px; font-weight:600; color:#1E1B4B; }
  #modalAuth .am-av-hint{ font-size:11px; color:#94A3B8; margin-top:2px; }
  #modalAuth .am-upl{
    margin-top:7px; font-size:12px; font-weight:700; color:#6C63FF;
    border:1.5px solid #C5C9FF; border-radius:8px; padding:5px 12px; transition:.15s;
  }
  #modalAuth .am-upl:hover{ background:#EEF2FF; }

  #modalAuth .am-sub-btn{
    width:100%; margin-top:6px; padding:12px; border-radius:12px;
    background:linear-gradient(135deg,#6C63FF,#5A52D5); color:#fff;
    font-size:14px; font-weight:700; box-shadow:0 8px 22px rgba(108,99,255,.32);
    transition:transform .12s, box-shadow .12s, opacity .15s;
  }
  #modalAuth .am-sub-btn:hover{ transform:translateY(-1px); box-shadow:0 12px 28px rgba(108,99,255,.4); }
  #modalAuth .am-sub-btn:disabled{ opacity:.7; cursor:not-allowed; transform:none; }
  #modalAuth .am-spin{ display:inline-block; height:16px; width:16px; border-radius:9999px; border:2px solid rgba(255,255,255,.5); border-top-color:#fff; animation:amSpin .7s linear infinite; }
  @keyframes amSpin{ to{ transform:rotate(360deg);} }

  #modalAuth .am-switch{ margin-top:14px; text-align:center; font-size:12.5px; color:#64748B; }
  #modalAuth .am-switch button{ font-weight:700; color:#6C63FF; }
  #modalAuth .am-switch button:hover{ text-decoration:underline; }
  #modalAuth .am-tos{ margin-top:12px; text-align:center; font-size:11px; color:#94A3B8; line-height:1.5; }
  #modalAuth .am-tos a{ color:#6C63FF; }

  @media (max-width:480px){
    #modalAuth .am-head{ padding:22px 20px 0; }
    #modalAuth .am-tabs{ margin:16px 20px 0; }
    #modalAuth .am-body{ padding:16px 20px 22px; }
    #modalAuth .am-row{ grid-template-columns:1fr; gap:0; }
    #modalAuth .am-row .am-fg-sm{ max-width:none; }
  }
</style>

<script>
(function(){
  // Read live at call time. This partial is included in the layout BEFORE the
  // `window.__APP__ = {…}` script, so capturing it once here would freeze APP to
  // {} — and then APP.urls.base would throw on submit, hanging the button spinner
  // forever. A getter always sees the real config once the page has parsed.
  function APP(){ return window.__APP__ || {}; }
  var T = window.Tanbat || (window.Tanbat = {});
  function toast(m,k){ (T.toast || function(){})(m,k); }

  var COUNTRIES=[
    ['AF','Afghanistan','🇦🇫'],['AL','Albania','🇦🇱'],['DZ','Algeria','🇩🇿'],['AR','Argentina','🇦🇷'],['AU','Australia','🇦🇺'],['AT','Austria','🇦🇹'],['BD','Bangladesh','🇧🇩'],['BE','Belgium','🇧🇪'],['BR','Brazil','🇧🇷'],['CA','Canada','🇨🇦'],['CN','China','🇨🇳'],['CO','Colombia','🇨🇴'],['DK','Denmark','🇩🇰'],['EG','Egypt','🇪🇬'],['FI','Finland','🇫🇮'],['FR','France','🇫🇷'],['DE','Germany','🇩🇪'],['GR','Greece','🇬🇷'],['IN','India','🇮🇳'],['ID','Indonesia','🇮🇩'],['IE','Ireland','🇮🇪'],['IL','Israel','🇮🇱'],['IT','Italy','🇮🇹'],['JP','Japan','🇯🇵'],['KE','Kenya','🇰🇪'],['MY','Malaysia','🇲🇾'],['MX','Mexico','🇲🇽'],['NL','Netherlands','🇳🇱'],['NZ','New Zealand','🇳🇿'],['NG','Nigeria','🇳🇬'],['NO','Norway','🇳🇴'],['PK','Pakistan','🇵🇰'],['PH','Philippines','🇵🇭'],['PL','Poland','🇵🇱'],['PT','Portugal','🇵🇹'],['RU','Russia','🇷🇺'],['SA','Saudi Arabia','🇸🇦'],['SG','Singapore','🇸🇬'],['ZA','South Africa','🇿🇦'],['ES','Spain','🇪🇸'],['LK','Sri Lanka','🇱🇰'],['SE','Sweden','🇸🇪'],['CH','Switzerland','🇨🇭'],['TH','Thailand','🇹🇭'],['TR','Turkey','🇹🇷'],['AE','United Arab Emirates','🇦🇪'],['GB','United Kingdom','🇬🇧'],['US','United States','🇺🇸'],['VN','Vietnam','🇻🇳']
  ];
  var $ = function(id){ return document.getElementById(id); };
  var modal = $('modalAuth');
  if (!modal) return;

  var csel = $('amRCountry');
  COUNTRIES.forEach(function(c){ csel.add(new Option(c[2]+' '+c[1], c[0])); });

  var EYE_OPEN='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
  var EYE_SHUT='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>';
  document.querySelectorAll('#modalAuth .am-eye').forEach(function(b){ b.innerHTML=EYE_OPEN; });

  // ── Tab switching ──
  function switchTab(which){
    var isReg = which === 'register';
    $('amTabs').classList.toggle('is-reg', isReg);
    document.querySelectorAll('#modalAuth .am-tab').forEach(function(t){
      t.classList.toggle('is-active', t.dataset.amTab === which);
    });
    $('amLoginForm').classList.toggle('show', !isReg);
    $('amRegForm').classList.toggle('show', isReg);
    $('amEyebrow').textContent = isReg ? 'Join Tanbat' : 'Welcome back';
    $('amTitle').textContent   = isReg ? 'Create your account' : 'Sign in to Tanbat';
    $('amSub').textContent     = isReg ? 'It only takes a minute to get started.'
                                       : 'Sign in to like, comment and join the conversation.';
  }

  // ── Public entry point ──
  window.openAuthModal = function(mode){
    switchTab(mode === 'register' ? 'register' : 'login');
    T.openModal ? T.openModal('modalAuth') : modal.classList.remove('hidden');
    setTimeout(function(){ $(mode === 'register' ? 'amRName' : 'amLId').focus(); }, 60);
  };

  // ── Wiring ──
  modal.addEventListener('click', function(e){
    var tabBtn = e.target.closest('[data-am-tab]');
    if (tabBtn){ switchTab(tabBtn.dataset.amTab); return; }
    var eye = e.target.closest('[data-am-eye]');
    if (eye){
      var inp = $(eye.dataset.amEye); var show = inp.type === 'password';
      inp.type = show ? 'text' : 'password'; eye.innerHTML = show ? EYE_SHUT : EYE_OPEN;
    }
  });

  document.querySelectorAll('#modalAuth .am-inp').forEach(function(el){
    el.addEventListener('input', function(){
      this.classList.remove('err');
      var fe = this.closest('.am-fg') && this.closest('.am-fg').querySelector('.am-err');
      if (fe) fe.classList.remove('on');
    });
  });

  // Avatar
  var picFile = null;
  $('amAvBtn').addEventListener('click', function(){ $('amPicIn').click(); });
  $('amAvRing').addEventListener('click', function(){ $('amPicIn').click(); });
  $('amPicIn').addEventListener('change', function(e){
    var f = e.target.files[0]; if (!f) return;
    if (!f.type.startsWith('image/')){ toast('Please upload an image file.','bad'); return; }
    if (f.size > 5*1024*1024){ toast('Image must be under 5 MB.','bad'); return; }
    picFile = f;
    var r = new FileReader();
    r.onload = function(ev){
      var img = $('amAvImg'); img.src = ev.target.result; img.style.display='block';
      $('amAvPh').style.display='none';
      $('amAvRing').classList.remove('err'); $('amRAvE').classList.remove('on');
    };
    r.readAsDataURL(f);
  });

  function ok(id,eid){ $(id).classList.remove('err'); $(eid).classList.remove('on'); return true; }
  function bad(id,eid,msg){ var el=$(id); if(el)el.classList.add('err'); var e=$(eid); if(e){ if(msg)e.textContent=msg; e.classList.add('on'); } return false; }
  function chk(id,eid,fn){ return fn($(id).value) ? ok(id,eid) : bad(id,eid); }
  function chkSel(id,eid){ return $(id).value ? ok(id,eid) : bad(id,eid); }

  var REG_MAP={name:['amRName','amRNameE'],age:['amRAge','amRAgeE'],gender:['amRGender','amRGenderE'],country:['amRCountry','amRCountryE'],email:['amREmail','amREmailE'],username:['amRUser','amRUserE'],password:['amRPw','amRPwE'],profile_picture:['amAvRing','amRAvE']};
  function applyServerErrors(errors){
    if(!errors || typeof errors!=='object') return;
    for(var f in errors){ var m=REG_MAP[f]; if(!m) continue; var msg=Array.isArray(errors[f])?errors[f][0]:String(errors[f]); bad(m[0],m[1],msg); }
  }

  function busy(btn,on,label){ btn.disabled=on; btn.innerHTML = on ? '<span class="am-spin"></span>' : label; }

  // fetch with a hard timeout so the submit button can NEVER spin forever if the
  // network/request hangs. Aborts after `ms` and rejects, which the callers turn
  // into a reset button + error toast.
  function fetchT(url, opts, ms){
    ms = ms || 20000;
    if (typeof AbortController === 'undefined') return fetch(url, opts);
    var c = new AbortController();
    var timer = setTimeout(function(){ c.abort(); }, ms);
    var merged = {}; for (var k in opts) merged[k] = opts[k]; merged.signal = c.signal;
    return fetch(url, merged).then(function(r){ clearTimeout(timer); return r; },
                                   function(err){ clearTimeout(timer); throw err; });
  }

  // Where to send the user once authenticated. Prefer an explicit home
  // navigation (what the old landing login did) over location.reload() so a
  // stuck reload can't leave the spinner up.
  function goAuthed(){ var a=APP(); location.assign((a.urls && a.urls.home) || (a.urls && a.urls.base) || '/'); }

  // ── Login ──
  $('amLoginForm').addEventListener('submit', function(e){
    e.preventDefault();
    var v1=chk('amLId','amLIdE',function(v){return v.trim().length>0;});
    var v2=chk('amLPw','amLPwE',function(v){return v.length>0;});
    if(!v1||!v2) return;
    var btn=$('amBtnLogin'); busy(btn,true);
    fetchT(APP().urls.base + '/auth/login',{
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':APP().csrf},
      credentials:'same-origin',
      body:JSON.stringify({identifier:$('amLId').value.trim(),password:$('amLPw').value})
    }).then(function(res){ return res.json().catch(function(){return {success:false,message:'Server error ('+res.status+').'};}); })
      .then(function(data){
        if(data.success){ goAuthed(); return; }
        busy(btn,false,'Sign In');
        bad('amLPw','amLPwE',data.message||'Invalid credentials.');
        toast(data.message||'Invalid credentials.','bad');
      }).catch(function(err){
        busy(btn,false,'Sign In');
        toast(err && err.name === 'AbortError' ? 'Request timed out — please try again.' : 'Connection error.','bad');
      });
  });

  // ── Register ──
  $('amRegForm').addEventListener('submit', function(e){
    e.preventDefault();
    var v1=chk('amRName','amRNameE',function(v){return v.trim().length>0;});
    var v2=chk('amRAge','amRAgeE',function(v){return v && parseInt(v)>=13 && parseInt(v)<=120;});
    var v3=chkSel('amRGender','amRGenderE');
    var v4=chkSel('amRCountry','amRCountryE');
    var v5=chk('amREmail','amREmailE',function(v){return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);});
    var v6=chk('amRUser','amRUserE',function(v){return v.trim().length>0;});
    var v7=chk('amRPw','amRPwE',function(v){return v.length>=8;});
    var v8=true; if(!picFile){ $('amAvRing').classList.add('err'); $('amRAvE').classList.add('on'); v8=false; }
    if(![v1,v2,v3,v4,v5,v6,v7,v8].every(Boolean)) return;
    var btn=$('amBtnReg'); busy(btn,true);
    var fd=new FormData();
    fd.append('name',$('amRName').value.trim());
    fd.append('age',$('amRAge').value);
    fd.append('gender',$('amRGender').value);
    fd.append('country',$('amRCountry').value);
    fd.append('email',$('amREmail').value.trim());
    fd.append('username',$('amRUser').value.trim());
    fd.append('password',$('amRPw').value);
    fd.append('profile_picture',picFile);
    fetchT(APP().urls.base + '/auth/register',{
      method:'POST',
      headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':APP().csrf},
      credentials:'same-origin',
      body:fd
    }, 40000).then(function(res){ return res.json().catch(function(){return {success:false,message:'Server error ('+res.status+').'};}); })
      .then(function(data){
        if(data.success){ toast('Welcome, '+(data.user&&data.user.name?data.user.name:'')+'!','ok'); setTimeout(goAuthed,600); return; }
        busy(btn,false,'Create account');
        applyServerErrors(data.errors);
        toast(data.message||'Registration failed.','bad');
      }).catch(function(err){
        busy(btn,false,'Create account');
        toast(err && err.name === 'AbortError' ? 'Request timed out — please try again.' : 'Connection error.','bad');
      });
  });
})();
</script>
