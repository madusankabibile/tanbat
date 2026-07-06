@extends('layouts.app')
@section('title', 'Tanbat — Connect & Share')

@push('head')
<style>
:root{
  --p:#6C63FF;--pd:#5A52D5;--pl:#EEF0FF;
  --a:#FF6584;--al:#FFF0F3;
  --td:#1E1B4B;--tm:#4B5563;--tl:#9CA3AF;
  --bg:#F7F8FF;--white:#FFFFFF;
  --bd:#E5E7EB;--ok:#10B981;--err:#EF4444;
  --glass:rgba(255,255,255,.72);
  --glass-bd:rgba(255,255,255,.55);
  --sh:0 30px 70px rgba(108,99,255,.22),0 8px 24px rgba(255,101,132,.08);
}

/* ────── Page shell ────── */
.lp{
  min-height:100vh;min-height:100dvh;
  display:flex;flex-direction:column;
  color:var(--td);position:relative;overflow-x:hidden;
  background:
    radial-gradient(60% 50% at 12% 8%,rgba(108,99,255,.18) 0%,transparent 55%),
    radial-gradient(55% 50% at 92% 88%,rgba(255,101,132,.16) 0%,transparent 55%),
    radial-gradient(40% 40% at 78% 22%,rgba(168,85,247,.10) 0%,transparent 55%),
    linear-gradient(180deg,#FAFBFF 0%,#F1F2FE 100%);
}
.lp::before{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
  background-image:
    linear-gradient(rgba(108,99,255,.05) 1px,transparent 1px),
    linear-gradient(90deg,rgba(108,99,255,.05) 1px,transparent 1px);
  background-size:48px 48px;
  mask-image:radial-gradient(circle at 50% 35%,black 0%,transparent 70%);
  -webkit-mask-image:radial-gradient(circle at 50% 35%,black 0%,transparent 70%);
}

/* ────── Floating blobs (decorative) ────── */
.blob{position:fixed;border-radius:50%;filter:blur(80px);pointer-events:none;z-index:0;will-change:transform}
.b1{width:520px;height:520px;background:rgba(108,99,255,.22);top:-120px;right:-80px;animation:float1 16s ease-in-out infinite}
.b2{width:440px;height:440px;background:rgba(255,101,132,.18);bottom:-100px;left:-80px;animation:float2 19s ease-in-out infinite}
.b3{width:300px;height:300px;background:rgba(168,85,247,.14);top:55%;left:48%;animation:float3 22s ease-in-out infinite}
@keyframes float1{0%,100%{transform:translate(0,0)}50%{transform:translate(-32px,28px)}}
@keyframes float2{0%,100%{transform:translate(0,0)}50%{transform:translate(28px,-22px)}}
@keyframes float3{0%,100%{transform:translate(0,0)}50%{transform:translate(-16px,-22px)}}

/* ────── Top nav ────── */
.lp-nav{
  position:sticky;top:0;z-index:50;
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 24px;
  background:rgba(255,255,255,.6);
  backdrop-filter:saturate(140%) blur(14px);
  -webkit-backdrop-filter:saturate(140%) blur(14px);
  border-bottom:1px solid rgba(255,255,255,.6);
}
.brand{display:flex;align-items:center;gap:10px;text-decoration:none}
.brand-ico{
  width:38px;height:38px;border-radius:11px;
  background:linear-gradient(135deg,var(--p),var(--a));
  display:grid;place-items:center;color:#fff;font-weight:800;font-size:18px;
  box-shadow:0 6px 16px rgba(108,99,255,.38),inset 0 1px 0 rgba(255,255,255,.4);
  letter-spacing:-1px;
}
.brand-name{font:800 21px/1 'Inter',sans-serif;background:linear-gradient(135deg,var(--p),var(--a));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.nav-r{display:flex;gap:8px;align-items:center}
.btn-ghost-l{padding:8px 16px;border:none;background:none;color:var(--tm);font:600 13.5px 'Inter',sans-serif;cursor:pointer;border-radius:9px;transition:.2s}
.btn-ghost-l:hover{background:rgba(108,99,255,.08);color:var(--p)}
.btn-pri{
  padding:9px 20px;color:#fff;border:none;border-radius:11px;
  background:linear-gradient(135deg,var(--p),var(--pd));
  font:600 13.5px 'Inter',sans-serif;cursor:pointer;
  box-shadow:0 6px 18px rgba(108,99,255,.42),inset 0 1px 0 rgba(255,255,255,.25);
  transition:.2s;
}
.btn-pri:hover{transform:translateY(-1px);box-shadow:0 10px 24px rgba(108,99,255,.5)}

/* ────── Hero grid ────── */
.hero{
  flex:1;display:grid;grid-template-columns:1.05fr 1fr;
  gap:60px;align-items:center;
  max-width:1240px;width:100%;margin:0 auto;
  padding:40px 32px 60px;
  position:relative;z-index:1;
}
.hero-l{max-width:560px}
.badge{
  display:inline-flex;align-items:center;gap:8px;
  padding:6px 14px 6px 10px;
  background:rgba(255,255,255,.65);
  border:1px solid rgba(108,99,255,.22);
  border-radius:999px;color:var(--p);
  font:600 12px 'Inter',sans-serif;letter-spacing:.4px;
  margin-bottom:22px;backdrop-filter:blur(8px);
}
.dot{width:7px;height:7px;background:var(--p);border-radius:50%;animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(.78)}}

.hero-title{
  font:800 clamp(34px,4.6vw,58px)/1.08 'Inter',sans-serif;
  letter-spacing:-1.4px;color:var(--td);margin:0 0 18px;
}
.hero-title .hl{
  background:linear-gradient(135deg,var(--p) 0%,var(--a) 70%,#F59E0B 100%);
  -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
  display:inline-block;
}
.hero-desc{font:400 16px/1.7 'Inter',sans-serif;color:var(--tm);margin-bottom:30px;max-width:460px}

/* Trust chips: avatar stack + rating */
.trust{display:flex;align-items:center;gap:14px;margin-bottom:28px;flex-wrap:wrap}
.avstack{display:flex}
.avstack span{
  width:34px;height:34px;border-radius:50%;
  background:linear-gradient(135deg,#6C63FF,#FF6584);
  border:2.5px solid #fff;display:grid;place-items:center;
  color:#fff;font:700 12px 'Inter';margin-left:-10px;
  box-shadow:0 2px 6px rgba(0,0,0,.08);
}
.avstack span:first-child{margin-left:0;background:linear-gradient(135deg,#F59E0B,#EF4444)}
.avstack span:nth-child(2){background:linear-gradient(135deg,#10B981,#0EA5E9)}
.avstack span:nth-child(3){background:linear-gradient(135deg,#A855F7,#6C63FF)}
.avstack span:nth-child(4){background:linear-gradient(135deg,#FF6584,#F59E0B)}
.trust-txt{font:600 13px/1.4 'Inter';color:var(--tm)}
.trust-txt b{color:var(--td);font-weight:800}
.trust-stars{color:#F59E0B;letter-spacing:1px;font-size:13px}

.stats{display:flex;gap:24px;align-items:stretch}
.stat-cell{
  padding:14px 18px;border-radius:14px;
  background:rgba(255,255,255,.5);
  border:1px solid rgba(255,255,255,.7);
  backdrop-filter:blur(8px);
  flex:1;min-width:0;
}
.snum{font:800 22px/1 'Inter';color:var(--td);letter-spacing:-.5px}
.slbl{font:500 11.5px/1 'Inter';color:var(--tl);margin-top:5px;text-transform:uppercase;letter-spacing:.6px}

/* ────── Glass auth card ────── */
.auth-wrap{display:flex;justify-content:flex-end;position:relative;z-index:2}
.auth-card{
  position:relative;width:430px;max-width:100%;
  background:var(--glass);
  border:1px solid var(--glass-bd);
  border-radius:24px;
  box-shadow:var(--sh);
  backdrop-filter:saturate(150%) blur(22px);
  -webkit-backdrop-filter:saturate(150%) blur(22px);
  padding:28px 30px;
  transition:padding .35s ease;
}
.auth-card::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  border-radius:inherit;
  background:linear-gradient(135deg,rgba(255,255,255,.6) 0%,rgba(255,255,255,0) 50%,rgba(108,99,255,.05) 100%);
}
.auth-card > *{position:relative}

.card-hd{margin-bottom:18px}
.card-eyebrow{font:700 11.5px/1 'Inter';letter-spacing:1.4px;text-transform:uppercase;color:var(--p);margin-bottom:8px}
.card-title{font:800 24px/1.15 'Inter';color:var(--td);letter-spacing:-.5px;margin-bottom:5px}
.card-sub{font:400 13.5px/1.5 'Inter';color:var(--tl)}
.card-sub .lnk{color:var(--p);font-weight:700;cursor:pointer;text-decoration:none}
.card-sub .lnk:hover{text-decoration:underline}

/* Tabs (segmented) */
.tabs{
  display:grid;grid-template-columns:1fr 1fr;
  background:rgba(108,99,255,.08);
  border-radius:12px;padding:4px;
  margin-bottom:20px;gap:2px;position:relative;
}
.tab{
  padding:9px;border:none;background:none;
  border-radius:9px;font:600 13.5px 'Inter';color:var(--tm);
  cursor:pointer;transition:color .2s;position:relative;z-index:1;
}
.tab.active{color:#fff}
.tab-pill{
  position:absolute;top:4px;bottom:4px;left:4px;
  width:calc(50% - 4px);
  background:linear-gradient(135deg,var(--p),var(--pd));
  border-radius:9px;
  box-shadow:0 4px 12px rgba(108,99,255,.35);
  transition:transform .35s cubic-bezier(.6,-.05,.4,1.2);
}
.tabs.is-reg .tab-pill{transform:translateX(100%)}

/* Form */
.forms{position:relative;overflow:hidden}
.fpane{display:none;animation:fadeIn .25s ease}
.fpane.show{display:block}
@keyframes fadeIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:none}}

.fg{margin-bottom:13px}
.lbl{display:block;font:600 12.5px/1 'Inter';color:var(--tm);margin-bottom:7px;letter-spacing:.2px}
.req{color:var(--a);margin-left:2px}
.inp,.sel{
  width:100%;padding:12px 14px;
  border:1.5px solid rgba(229,231,235,.85);
  border-radius:11px;background:rgba(255,255,255,.85);
  font:400 14px 'Inter';color:var(--td);
  outline:none;transition:.2s;
}
.inp:focus,.sel:focus{border-color:var(--p);background:#fff;box-shadow:0 0 0 4px rgba(108,99,255,.13)}
.inp::placeholder{color:var(--tl)}
.inp.err,.sel.err{border-color:var(--err);box-shadow:0 0 0 4px rgba(239,68,68,.1)}
.ferr{font:500 11.5px/1.3 'Inter';color:var(--err);margin-top:5px;display:none}
.ferr.on{display:block}
.iw{position:relative}
.iw .inp{padding-right:42px}
.eye{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  background:none;border:none;cursor:pointer;color:var(--tl);
  padding:6px;border-radius:6px;transition:.2s;
}
.eye:hover{color:var(--p);background:rgba(108,99,255,.08)}
.sel{
  background:rgba(255,255,255,.85) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='%239CA3AF' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E") no-repeat right 14px center;
  appearance:none;-webkit-appearance:none;cursor:pointer;padding-right:38px;
}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:11px}

/* Avatar pick */
.av-wrap{display:flex;align-items:center;gap:14px}
.av-ring{
  width:72px;height:72px;border-radius:50%;
  background:rgba(108,99,255,.08);
  border:2px dashed var(--p);
  display:grid;place-items:center;overflow:hidden;flex-shrink:0;
  cursor:pointer;transition:.2s;position:relative;
}
.av-ring:hover{background:rgba(108,99,255,.13);transform:scale(1.04)}
.av-ring.err{border-color:var(--err);background:rgba(239,68,68,.06)}
#avImg{width:100%;height:100%;object-fit:cover;display:none}
.av-ph{color:var(--p);display:grid;place-items:center}
.av-info .av-lbl{font:600 13px/1 'Inter';color:var(--tm);margin-bottom:4px}
.av-info .av-hint{font:400 11.5px/1.3 'Inter';color:var(--tl);margin-bottom:7px}
.btn-upl{
  padding:6px 13px;border:1.5px solid var(--bd);border-radius:8px;
  background:#fff;font:500 12.5px 'Inter';color:var(--tm);cursor:pointer;transition:.2s;
}
.btn-upl:hover{border-color:var(--p);color:var(--p);background:var(--pl)}
#picIn{display:none}

.forgot{
  display:block;text-align:right;
  font:600 12.5px 'Inter';color:var(--p);
  text-decoration:none;margin:-4px 0 14px;
}
.forgot:hover{text-decoration:underline}

.btn-sub{
  width:100%;padding:13px;
  background:linear-gradient(135deg,var(--p),var(--pd));
  color:#fff;border:none;border-radius:12px;
  font:700 14.5px 'Inter';cursor:pointer;letter-spacing:.3px;
  box-shadow:0 8px 22px rgba(108,99,255,.42),inset 0 1px 0 rgba(255,255,255,.2);
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:.2s;margin-top:4px;
}
.btn-sub:hover{transform:translateY(-2px);box-shadow:0 12px 30px rgba(108,99,255,.5)}
.btn-sub:disabled{opacity:.65;cursor:not-allowed;transform:none}

.divdr{
  display:flex;align-items:center;gap:10px;
  margin:16px 0 12px;color:var(--tl);font:500 11.5px 'Inter';letter-spacing:.4px;
}
.divdr::before,.divdr::after{content:'';flex:1;height:1px;background:rgba(229,231,235,.85)}

.soc{display:grid;grid-template-columns:1fr 1fr;gap:9px}
.btn-soc{
  padding:10px;border:1.5px solid rgba(229,231,235,.85);
  border-radius:11px;background:rgba(255,255,255,.7);
  font:600 13px 'Inter';color:var(--tm);cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;transition:.2s;
}
.btn-soc:hover{border-color:var(--p);color:var(--p);background:#fff;transform:translateY(-1px)}

.spin{width:17px;height:17px;border:2.5px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:rot .7s linear infinite;display:inline-block;flex-shrink:0}
@keyframes rot{to{transform:rotate(360deg)}}

.tos{font:400 11.5px/1.5 'Inter';color:var(--tl);text-align:center;margin-top:14px}
.tos a{color:var(--p);font-weight:600;text-decoration:none}
.tos a:hover{text-decoration:underline}

/* ────── Tablet ────── */
@media (max-width:980px){
  .hero{
    grid-template-columns:1fr;
    gap:28px;padding:24px 20px 44px;
    text-align:center;
  }
  .hero-l{margin:0 auto;max-width:520px}
  .hero-desc{margin-left:auto;margin-right:auto}
  .trust,.stats{justify-content:center}
  .auth-wrap{justify-content:center;width:100%}
  .auth-card{width:100%;max-width:460px}
  .nav-r .btn-ghost-l{display:none}
}

/* ────── Mobile ────── */
@media (max-width:640px){
  .lp-nav{padding:12px 16px}
  .brand-name{font-size:19px}
  .nav-r{gap:6px}
  .btn-pri{padding:8px 14px;font-size:12.5px;border-radius:9px}

  .hero{padding:18px 14px 30px;gap:0}

  /* Mobile: drop the hero pitch entirely — auth card takes focus */
  .hero-l{display:none}

  .auth-card{
    padding:22px 18px;
    border-radius:20px;
    width:100%;
  }
  .card-eyebrow{font-size:10.5px;letter-spacing:1.2px}
  .card-title{font-size:21px}
  .card-sub{font-size:12.5px}

  .tabs{margin-bottom:16px}
  .tab{font-size:13px;padding:9px}

  .lbl{font-size:11.5px;margin-bottom:5px}
  .inp,.sel{padding:11px 12px;font-size:15px;border-radius:10px}  /* 15px = no iOS zoom */
  .frow{grid-template-columns:1fr;gap:0}
  .btn-sub{padding:13px;font-size:14px;border-radius:11px}
  .av-wrap{gap:12px}
  .av-ring{width:64px;height:64px}
  .av-info .av-lbl{font-size:12.5px}

  .soc{grid-template-columns:1fr 1fr;gap:8px}
  .btn-soc{padding:11px 8px;font-size:12.5px}

  .blob{opacity:.6}
  .b3{display:none}
}

/* Very small phones */
@media (max-width:360px){
  .hero{padding:14px 10px 24px}
  .auth-card{padding:20px 14px;border-radius:18px}
  .hero-title{font-size:30px}
}
</style>
@endpush

@section('content')
<div class="lp">
  <div class="blob b1"></div>
  <div class="blob b2"></div>
  <div class="blob b3"></div>

  <nav class="lp-nav">
    <a href="{{ url('/') }}" class="brand">
      <div class="brand-ico">T</div>
      <span class="brand-name">Tanbat</span>
    </a>
    <div class="nav-r">
      <button class="btn-ghost-l" type="button" onclick="switchTab('login')">Sign In</button>
      <button class="btn-pri" type="button" onclick="switchTab('register')">Join Free</button>
    </div>
  </nav>

  <div class="hero">
    {{-- Left: visual / pitch --}}
    <div class="hero-l">
      <div class="badge"><span class="dot"></span>Now in public beta</div>
      <h1 class="hero-title">Where moments <br><span class="hl">become a movement.</span></h1>
      <p class="hero-desc">Share your story, follow what matters, and find people who get you. Tanbat is the social home you've been waiting for.</p>

      <div class="trust">
        <div class="avstack" aria-hidden="true"><span>A</span><span>M</span><span>R</span><span>J</span></div>
        <div class="trust-txt">
          <div><b>12M+</b> creators already on board</div>
          <div class="trust-stars">★★★★★ <span style="color:var(--tl);font-weight:500">4.9 rating</span></div>
        </div>
      </div>

      <div class="stats">
        <div class="stat-cell"><div class="snum">12M+</div><div class="slbl">Active users</div></div>
        <div class="stat-cell"><div class="snum">180+</div><div class="slbl">Countries</div></div>
        <div class="stat-cell"><div class="snum">99.9%</div><div class="slbl">Uptime</div></div>
      </div>
    </div>

    {{-- Right: auth glass card --}}
    <div class="auth-wrap">
      <div class="auth-card">
        <div class="card-hd">
          <div class="card-eyebrow" id="cardEyebrow">Welcome back</div>
          <div class="card-title" id="cardTitle">Sign in to Tanbat</div>
          <div class="card-sub" id="cardSub">
            New here? <span class="lnk" onclick="switchTab('register')">Create a free account</span>
          </div>
        </div>

        <div class="tabs" id="tabs">
          <div class="tab-pill" id="tabPill"></div>
          <button class="tab active" id="tabL" type="button" onclick="switchTab('login')">Sign In</button>
          <button class="tab" id="tabR" type="button" onclick="switchTab('register')">Register</button>
        </div>

        <div class="forms">

          {{-- LOGIN PANE --}}
          <div class="fpane show" id="paneLogin">
            <form id="loginForm" onsubmit="doLogin(event)" novalidate>
              <div class="fg">
                <label class="lbl" for="lId">Email or Username</label>
                <input class="inp" id="lId" type="text" placeholder="you@example.com" autocomplete="username">
                <div class="ferr" id="lIdE">Enter your email or username.</div>
              </div>
              <div class="fg">
                <label class="lbl" for="lPw">Password</label>
                <div class="iw">
                  <input class="inp" id="lPw" type="password" placeholder="••••••••" autocomplete="current-password">
                  <button type="button" class="eye" onclick="togglePw('lPw',this)" tabindex="-1" aria-label="Toggle password">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
                <div class="ferr" id="lPwE">Enter your password.</div>
              </div>
              <a class="forgot" href="#">Forgot password?</a>
              <button type="submit" class="btn-sub" id="btnLogin">Sign In</button>
            </form>

            <div class="divdr">or continue with</div>
            <div class="soc">
              <button class="btn-soc" type="button" onclick="socialMsg('Google')">
                <svg width="16" height="16" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                Google
              </button>
              <button class="btn-soc" type="button" onclick="socialMsg('Apple')">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/></svg>
                Apple
              </button>
            </div>
          </div>

          {{-- REGISTER PANE --}}
          <div class="fpane" id="paneReg">
            <form id="regForm" onsubmit="doRegister(event)" novalidate>
              <div class="frow">
                <div class="fg">
                  <label class="lbl" for="rName">Full Name <span class="req">*</span></label>
                  <input class="inp" id="rName" type="text" placeholder="Jane Smith" autocomplete="name">
                  <div class="ferr" id="rNameE">Name is required.</div>
                </div>
                <div class="fg">
                  <label class="lbl" for="rAge">Age <span class="req">*</span></label>
                  <input class="inp" id="rAge" type="number" inputmode="numeric" placeholder="25" min="13" max="120">
                  <div class="ferr" id="rAgeE">Valid age required (13+).</div>
                </div>
              </div>
              <div class="frow">
                <div class="fg">
                  <label class="lbl" for="rGender">Gender <span class="req">*</span></label>
                  <select class="sel" id="rGender">
                    <option value="">Select gender</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="non-binary">Non-binary</option>
                    <option value="other">Other</option>
                    <option value="prefer_not">Prefer not to say</option>
                  </select>
                  <div class="ferr" id="rGenderE">Please select a gender.</div>
                </div>
                <div class="fg">
                  <label class="lbl" for="rCountry">Country <span class="req">*</span></label>
                  <select class="sel" id="rCountry"><option value="">🌍 Select country</option></select>
                  <div class="ferr" id="rCountryE">Country is required.</div>
                </div>
              </div>
              <div class="frow">
                <div class="fg">
                  <label class="lbl" for="rEmail">Email <span class="req">*</span></label>
                  <input class="inp" id="rEmail" type="email" inputmode="email" placeholder="you@example.com" autocomplete="email">
                  <div class="ferr" id="rEmailE">Valid email required.</div>
                </div>
                <div class="fg">
                  <label class="lbl" for="rUser">Username <span class="req">*</span></label>
                  <input class="inp" id="rUser" type="text" placeholder="jane_smith" autocomplete="username">
                  <div class="ferr" id="rUserE">Username is required.</div>
                </div>
              </div>
              <div class="fg">
                <label class="lbl" for="rPw">Password <span class="req">*</span></label>
                <div class="iw">
                  <input class="inp" id="rPw" type="password" placeholder="Min. 8 characters" autocomplete="new-password">
                  <button type="button" class="eye" onclick="togglePw('rPw',this)" tabindex="-1" aria-label="Toggle password">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                  </button>
                </div>
                <div class="ferr" id="rPwE">Minimum 8 characters required.</div>
              </div>
              <div class="fg">
                <label class="lbl">Profile Picture <span class="req">*</span></label>
                <div class="av-wrap">
                  <div class="av-ring" id="avRing" onclick="document.getElementById('picIn').click()">
                    <div class="av-ph" id="avPh">
                      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <img id="avImg" src="" alt="Avatar preview">
                  </div>
                  <div class="av-info">
                    <div class="av-lbl">Upload your photo</div>
                    <div class="av-hint">PNG, JPG or WEBP · Max 5 MB</div>
                    <button type="button" class="btn-upl" onclick="document.getElementById('picIn').click()">Choose File</button>
                  </div>
                </div>
                <div class="ferr" id="rAvE">Profile picture is required.</div>
                <input type="file" id="picIn" accept="image/*" onchange="onAvatar(event)">
              </div>
              <button type="submit" class="btn-sub" id="btnReg">Create Account</button>
              <div class="tos">By signing up you agree to our <a href="#">Terms</a> and <a href="{{ url('/privacy') }}">Privacy</a>.</div>
            </form>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
const COUNTRIES=[
  ['AF','Afghanistan','🇦🇫'],['AL','Albania','🇦🇱'],['DZ','Algeria','🇩🇿'],['AR','Argentina','🇦🇷'],['AU','Australia','🇦🇺'],['AT','Austria','🇦🇹'],['BD','Bangladesh','🇧🇩'],['BE','Belgium','🇧🇪'],['BR','Brazil','🇧🇷'],['CA','Canada','🇨🇦'],['CN','China','🇨🇳'],['CO','Colombia','🇨🇴'],['DK','Denmark','🇩🇰'],['EG','Egypt','🇪🇬'],['FI','Finland','🇫🇮'],['FR','France','🇫🇷'],['DE','Germany','🇩🇪'],['GR','Greece','🇬🇷'],['IN','India','🇮🇳'],['ID','Indonesia','🇮🇩'],['IE','Ireland','🇮🇪'],['IL','Israel','🇮🇱'],['IT','Italy','🇮🇹'],['JP','Japan','🇯🇵'],['KE','Kenya','🇰🇪'],['MY','Malaysia','🇲🇾'],['MX','Mexico','🇲🇽'],['NL','Netherlands','🇳🇱'],['NZ','New Zealand','🇳🇿'],['NG','Nigeria','🇳🇬'],['NO','Norway','🇳🇴'],['PK','Pakistan','🇵🇰'],['PH','Philippines','🇵🇭'],['PL','Poland','🇵🇱'],['PT','Portugal','🇵🇹'],['RU','Russia','🇷🇺'],['SA','Saudi Arabia','🇸🇦'],['SG','Singapore','🇸🇬'],['ZA','South Africa','🇿🇦'],['ES','Spain','🇪🇸'],['LK','Sri Lanka','🇱🇰'],['SE','Sweden','🇸🇪'],['CH','Switzerland','🇨🇭'],['TH','Thailand','🇹🇭'],['TR','Turkey','🇹🇷'],['AE','United Arab Emirates','🇦🇪'],['GB','United Kingdom','🇬🇧'],['US','United States','🇺🇸'],['VN','Vietnam','🇻🇳']
];
(function(){const s=document.getElementById('rCountry');COUNTRIES.forEach(([c,n,f])=>{s.add(new Option(`${f} ${n}`,c));});})();

const CSRF = window.__APP__.csrf;
const URLS = {
  login:    window.__APP__.urls.base + '/auth/login',
  register: window.__APP__.urls.base + '/auth/register',
};
let picFile=null;

function switchTab(which){
  const isReg = which === 'register';
  document.getElementById('tabs').classList.toggle('is-reg', isReg);
  document.getElementById('tabL').classList.toggle('active', !isReg);
  document.getElementById('tabR').classList.toggle('active', isReg);
  document.getElementById('paneLogin').classList.toggle('show', !isReg);
  document.getElementById('paneReg').classList.toggle('show', isReg);
  document.getElementById('cardEyebrow').textContent = isReg ? 'Get started' : 'Welcome back';
  document.getElementById('cardTitle').textContent   = isReg ? 'Create your account' : 'Sign in to Tanbat';
  document.getElementById('cardSub').innerHTML       = isReg
    ? 'Already have one? <span class="lnk" onclick="switchTab(\'login\')">Sign in</span>'
    : 'New here? <span class="lnk" onclick="switchTab(\'register\')">Create a free account</span>';
}

const EYE_OPEN=`<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>`;
const EYE_SHUT=`<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>`;
function togglePw(id,btn){const inp=document.getElementById(id);const show=inp.type==='password';inp.type=show?'text':'password';btn.innerHTML=show?EYE_SHUT:EYE_OPEN;}

function onAvatar(e){
  const f=e.target.files[0];if(!f)return;
  if(!f.type.startsWith('image/')){toast('Please upload an image file.','bad');return;}
  if(f.size>5*1024*1024){toast('Image must be under 5 MB.','bad');return;}
  picFile=f;
  const r=new FileReader();
  r.onload=ev=>{
    const img=document.getElementById('avImg');
    img.src=ev.target.result;img.style.display='block';
    document.getElementById('avPh').style.display='none';
    document.getElementById('avRing').classList.remove('err');
    document.getElementById('rAvE').classList.remove('on');
  };
  r.readAsDataURL(f);
}

function ok(id,eid){document.getElementById(id).classList.remove('err');document.getElementById(eid).classList.remove('on');return true;}
function bad(id,eid,msg){const el=document.getElementById(id);if(el)el.classList.add('err');const e=document.getElementById(eid);if(e){if(msg)e.textContent=msg;e.classList.add('on');}return false;}
function chk(id,eid,fn){const v=document.getElementById(id).value;return fn(v)?ok(id,eid):bad(id,eid);}
function chkSel(id,eid){return document.getElementById(id).value?ok(id,eid):bad(id,eid);}

const REG_FIELD_MAP={name:['rName','rNameE'],age:['rAge','rAgeE'],gender:['rGender','rGenderE'],country:['rCountry','rCountryE'],email:['rEmail','rEmailE'],username:['rUser','rUserE'],password:['rPw','rPwE'],profile_picture:['avRing','rAvE']};
function applyServerErrors(errors){if(!errors||typeof errors!=='object')return;for(const f in errors){const m=REG_FIELD_MAP[f];if(!m)continue;const msg=Array.isArray(errors[f])?errors[f][0]:String(errors[f]);bad(m[0],m[1],msg);}}

async function doLogin(e){
  e.preventDefault();
  const v1=chk('lId','lIdE',v=>v.trim().length>0);
  const v2=chk('lPw','lPwE',v=>v.length>0);
  if(!v1||!v2) return;
  const btn=document.getElementById('btnLogin');btn.disabled=true;btn.innerHTML='<span class="spin"></span>';
  try{
    const res=await fetch(URLS.login,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':CSRF},credentials:'same-origin',body:JSON.stringify({identifier:document.getElementById('lId').value.trim(),password:document.getElementById('lPw').value})});
    btn.disabled=false;btn.innerHTML='Sign In';
    const data=await res.json().catch(()=>({success:false,message:`Server error (${res.status}).`}));
    if(data.success){ location.href = window.__APP__.urls.home; }
    else { bad('lPw','lPwE'); document.getElementById('lPwE').textContent=data.message||'Invalid credentials.'; toast(data.message||'Invalid credentials.','bad'); }
  }catch(err){btn.disabled=false;btn.innerHTML='Sign In';toast('Connection error.','bad');}
}

async function doRegister(e){
  e.preventDefault();
  const v1=chk('rName','rNameE',v=>v.trim().length>0);
  const v2=chk('rAge','rAgeE',v=>v&&parseInt(v)>=13&&parseInt(v)<=120);
  const v3=chkSel('rGender','rGenderE');
  const v4=chkSel('rCountry','rCountryE');
  const v5=chk('rEmail','rEmailE',v=>/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v));
  const v6=chk('rUser','rUserE',v=>v.trim().length>0);
  const v7=chk('rPw','rPwE',v=>v.length>=8);
  let v8=true;if(!picFile){document.getElementById('avRing').classList.add('err');document.getElementById('rAvE').classList.add('on');v8=false;}
  if(![v1,v2,v3,v4,v5,v6,v7,v8].every(Boolean)) return;
  const btn=document.getElementById('btnReg');btn.disabled=true;btn.innerHTML='<span class="spin"></span>';
  const fd=new FormData();
  fd.append('name',document.getElementById('rName').value.trim());
  fd.append('age',document.getElementById('rAge').value);
  fd.append('gender',document.getElementById('rGender').value);
  fd.append('country',document.getElementById('rCountry').value);
  fd.append('email',document.getElementById('rEmail').value.trim());
  fd.append('username',document.getElementById('rUser').value.trim());
  fd.append('password',document.getElementById('rPw').value);
  fd.append('profile_picture',picFile);
  try{
    const res=await fetch(URLS.register,{method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':CSRF},credentials:'same-origin',body:fd});
    btn.disabled=false;btn.innerHTML='Create Account';
    const data=await res.json().catch(()=>({success:false,message:`Server error (${res.status}).`}));
    if(data.success){ toast(`Welcome, ${data.user.name}!`,'ok'); setTimeout(()=>{location.href=window.__APP__.urls.home;},700); }
    else { applyServerErrors(data.errors); toast(data.message||'Registration failed.','bad'); }
  }catch(err){btn.disabled=false;btn.innerHTML='Create Account';toast('Connection error.','bad');}
}

function socialMsg(p){toast(`${p} sign-in coming soon!`);}

function toast(msg, kind){
  const t=document.getElementById('toast');
  t.textContent=msg;
  t.className='pointer-events-none fixed left-1/2 bottom-8 z-[9999] -translate-x-1/2 rounded-xl px-5 py-3 text-sm font-medium text-white shadow-pop opacity-100 translate-y-0 transition-all duration-300';
  if(kind==='ok') t.style.background='#10B981';
  else if(kind==='bad') t.style.background='#EF4444';
  else t.style.background='#1E1B4B';
  clearTimeout(t._t);
  t._t=setTimeout(()=>{t.style.opacity='0';t.style.transform='translateX(-50%) translateY(16px)';},3200);
}

document.querySelectorAll('.inp,.sel').forEach(el=>{
  el.addEventListener('input',function(){
    this.classList.remove('err');
    const fe=this.closest('.fg')?.querySelector('.ferr');
    if(fe) fe.classList.remove('on');
  });
});
</script>
@endpush
