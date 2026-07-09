const o=window.__APP__||{},c=(e,n=document)=>n.querySelector(e),u=(e,n=document)=>Array.from(n.querySelectorAll(e)),r=e=>String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;"),l=e=>Number(e||0).toLocaleString(),f=()=>{var e;return(e=window.Tanbat)==null?void 0:e.api};function m(e){e&&u("#siteStats [data-site]").forEach(n=>{n.textContent=l(e[n.dataset.site])})}function $(e){var a;const n=c("#activeUsers");if(!n)return;if(!e||!e.length){n.innerHTML='<li class="px-3 py-4 text-center text-xs text-slate-400">No active users yet.</li>';return}const s=((a=o.urls)==null?void 0:a.profileBase)||"";n.innerHTML=e.map(t=>{const i=t.profile_picture?`<img src="${r(t.profile_picture)}" alt="">`:r((t.username||t.name||"U").charAt(0).toUpperCase()),p=t.username?`${s}/${encodeURIComponent(t.username)}`:"#";return`
      <li data-user-id="${t.id}" data-user-username="${r(t.username||"")}" role="link" tabindex="0">
        <a href="${p}" class="contents" data-user-link>
          <span class="av ${t.online?"is-online":""}">${i}<span class="dot"></span></span>
          <div class="who">
            <span class="name">${r(t.name||t.username||"User")}</span>
            <span class="meta">${l(t.posts)} post${t.posts===1?"":"s"}${t.online?" · online":""}</span>
          </div>
        </a>
      </li>
    `}).join("")}async function d(){var n,s;if(!document.querySelector("#siteStats")&&!document.querySelector("#activeUsers"))return;const e=f();if(e)try{const a=(s=(n=o.urls)==null?void 0:n.api)==null?void 0:s.sidebar;if(!a)return;const{stats:t,active_users:i}=await e(a);m(t),$(i||[])}catch(a){console.warn("Sidebar fetch failed",a);const t=c("#activeUsers");t&&(t.innerHTML=`<li class="px-3 py-4 text-center text-xs text-slate-400">Couldn't load active users.</li>`)}}window.Tanbat=window.Tanbat||{};window.Tanbat.loadSidebar=d;document.addEventListener("DOMContentLoaded",d);
