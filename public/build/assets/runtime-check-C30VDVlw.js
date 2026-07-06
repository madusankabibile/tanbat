(function(){const c="__tanbat_adblock_dismissed_v1";function l(){const t=document.createElement("div");return t.id="AdContainer",t.className="adsbox ad-banner ad-placement ad-unit ads ad-slot adsbygoogle ad-zone pub_300x250 pub_300x250m pub_728x90 text-ad textAd text_ad text-ads text-ad-links advertisement banner_ad",t.setAttribute("data-ad-client","ca-pub-0000000000000000"),t.setAttribute("data-ad-slot","0000000000"),t.setAttribute("aria-hidden","true"),t.style.cssText="position:absolute!important;left:-9999px!important;top:-9999px!important;width:300px!important;height:250px!important;display:block!important;visibility:visible!important;",t.innerHTML="&nbsp;",document.body.appendChild(t),new Promise(o=>{setTimeout(()=>{const a=window.getComputedStyle(t),e=!t.isConnected||t.offsetParent===null||t.offsetHeight===0||t.clientHeight===0||t.offsetWidth===0||a.display==="none"||a.visibility==="hidden"||a.opacity==="0";t.remove(),o(e)},350)})}function d(t,o=2500){return new Promise(a=>{const e=document.createElement("script");e.src=t,e.async=!0;let r=!1;const n=m=>{r||(r=!0,e.remove(),a(m))};e.onload=()=>n(!1),e.onerror=()=>n(!0),document.head.appendChild(e),setTimeout(()=>n(!0),o)})}async function u(t,o=2500){try{const a=new AbortController,e=setTimeout(()=>a.abort(),o);return await fetch(t,{method:"HEAD",mode:"no-cors",cache:"no-store",signal:a.signal}),clearTimeout(e),!1}catch{return!0}}async function i(){const[t,o,a,e]=await Promise.all([l(),d("https://pagead2.googlesyndication.com/pagead/show_ads.js"),d("https://securepubads.g.doubleclick.net/tag/js/gpt.js"),u("https://static.doubleclick.net/instream/ad_status.js")]);return t||o||a||e}function b(){var o;if(document.getElementById("adblock-banner"))return;const t=document.createElement("div");t.id="adblock-banner",t.setAttribute("role","dialog"),t.setAttribute("aria-modal","true"),t.setAttribute("aria-labelledby","adblock-banner-title"),t.innerHTML=`
      <div class="adblock-backdrop"></div>
      <div class="adblock-card">
        <div class="adblock-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 9v4"/><path d="M12 17h.01"/>
            <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          </svg>
        </div>
        <h2 id="adblock-banner-title" class="adblock-title">Please disable your ad-blocker</h2>
        <p class="adblock-text">
          We run this platform for free, supported by ads. To keep it that way,
          please disable your ad-blocker for this site and reload the page.
        </p>
        <button type="button" class="adblock-btn" id="adblock-reload">Reload page</button>
        <p class="adblock-hint">Thank you for supporting Tanbat.</p>
      </div>
    `,document.body.appendChild(t),document.body.classList.add("adblock-locked"),(o=document.getElementById("adblock-reload"))==null||o.addEventListener("click",()=>{location.reload()})}function p(){var t;(t=document.getElementById("adblock-banner"))==null||t.remove(),document.body.classList.remove("adblock-locked")}async function s(){try{if(sessionStorage.getItem(c)==="1")return}catch{}await i()&&b(),document.addEventListener("visibilitychange",async()=>{document.visibilityState==="visible"&&document.getElementById("adblock-banner")&&(await i()||p())})}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",s):s()})();
