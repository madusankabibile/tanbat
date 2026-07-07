const w=window.__APP__,g=[{key:"like",emoji:"👍",label:"Like",color:"#2563EB"},{key:"love",emoji:"❤️",label:"Love",color:"#F43F5E"},{key:"haha",emoji:"😆",label:"Haha",color:"#F59E0B"},{key:"wow",emoji:"😮",label:"Wow",color:"#F59E0B"},{key:"sad",emoji:"😢",label:"Sad",color:"#F59E0B"},{key:"angry",emoji:"😡",label:"Angry",color:"#EF4444"}],f=Object.fromEntries(g.map(e=>[e.key,e])),i=e=>String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");function b(e){return e?`${w.urls.profileBase}/${encodeURIComponent(e)}`:"#"}function _(e){if(!e)return"";const t=Date.parse(e);if(Number.isNaN(t))return"";const s=Math.max(0,Date.now()-t),a=6e4,n=60*a,o=24*n;if(s>=5*o)return"";if(s<a)return"just now";if(s<n)return`${Math.floor(s/a)} min ago`;if(s<o)return`${Math.floor(s/n)} hr ago`;const r=Math.floor(s/o);return`${r} day${r===1?"":"s"} ago`}function L(e){if(!e||e[0]!=="#")return!0;const t=e.length===4?e.slice(1).split("").map(o=>o+o).join(""):e.slice(1,7),s=parseInt(t.slice(0,2),16),a=parseInt(t.slice(2,4),16),n=parseInt(t.slice(4,6),16);return(s*299+a*587+n*114)/1e3>=160}function x(e){const t=b(e==null?void 0:e.username),s=e!=null&&e.id?`data-user-id="${e.id}"`:"";if(e!=null&&e.profile_picture)return`<a href="${t}" ${s} data-user-link><img class="avatar" src="${i(e.profile_picture)}" alt=""></a>`;const a=i(((e==null?void 0:e.username)||(e==null?void 0:e.name)||"U").charAt(0).toUpperCase());return`<a href="${t}" ${s} data-user-link><span class="avatar">${a}</span></a>`}function u(e,t,s){const a=e.user||{},n=i(a.name||a.username||"User"),o=i(_(e.created_at_iso)),r=Number(e.views_count||0).toLocaleString(),l=b(a.username),d=a.id?`data-user-id="${a.id}"`:"";return`
    <div class="post-head">
      ${x(a)}
      <div class="who">
        <a href="${l}" ${d} data-user-link class="name hover:underline">${n}</a>
        <span class="meta">
          ${o?`<span>${o}</span><span class="dot"></span>`:""}
          <span class="post-badge ${t}">${s}</span>
          <span class="dot"></span>
          <span class="views" title="${r} views" data-card-views>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <strong data-views-count>${r}</strong>
            <span class="views-label">views</span>
          </span>
        </span>
      </div>
      ${C(e)}
    </div>
  `}function C(e){const t=!!e.is_owner,s=!!e.saved,a=[];return t||(a.push(m("not_interested","Not interested","Show fewer posts like this",M())),a.push(m("hide","Hide post","Remove from your feed",S()))),a.push(`
    <div class="post-menu-save-wrap ${s?"is-saved":""}" data-save-wrap>
      <button type="button" class="post-menu-item ${s?"is-active":""}"
              data-post-act="save" role="menuitem">
        <span class="pmi-ic">${j(s)}</span>
        <span class="pmi-text">
          <span class="pmi-title">${s?"Unsave post":"Save post"}</span>
          <span class="pmi-sub">${s?"Removed from your saved items":"Open it later from your bookmarks"}</span>
        </span>
      </button>
      <button type="button" class="post-menu-save-toggle" data-post-act="save-to" aria-label="Save to folder">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
      <div class="post-save-submenu" data-save-submenu role="menu">
        <div class="psm-loading">Loading folders…</div>
      </div>
    </div>
  `),t&&(a.push('<div class="post-menu-sep"></div>'),a.push(m("edit","Edit post","Update your post content",E())),a.push(m("delete","Delete post","Permanently remove this post",B(),"is-danger"))),`
    <div class="post-menu-wrap" data-post-menu>
      <button type="button" class="post-menu" aria-label="Post options" data-post-menu-trigger>
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
      </button>
      <div class="post-menu-panel" role="menu">
        ${a.join("")}
      </div>
    </div>
  `}function m(e,t,s,a,n=""){return`
    <button type="button" class="post-menu-item ${n}" data-post-act="${e}" role="menuitem">
      <span class="pmi-ic">${a}</span>
      <span class="pmi-text">
        <span class="pmi-title">${t}</span>
        <span class="pmi-sub">${s}</span>
      </span>
    </button>
  `}function M(){return'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>'}function S(){return'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a19.77 19.77 0 0 1 4.22-5.06"/><path d="M1 1l22 22"/><path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a19.5 19.5 0 0 1-3.13 4.18"/><path d="M14.12 14.12A3 3 0 1 1 9.88 9.88"/></svg>'}function j(e){return`<svg viewBox="0 0 24 24" fill="${e?"currentColor":"none"}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>`}function E(){return'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'}function B(){return'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>'}function T(){return`
    <div class="reaction-pop" role="menu" aria-label="Pick a reaction">
      ${g.map(e=>`
        <button type="button" class="reaction-pop-btn" data-react="${e.key}" title="${e.label}" aria-label="${e.label}">
          <span class="re">${e.emoji}</span>
        </button>`).join("")}
    </div>`}function $(e){return(e||[]).slice(0,3).map(s=>{var a;return`<span class="re-chip">${((a=f[s])==null?void 0:a.emoji)||"👍"}</span>`}).join("")}function A(){return'<svg class="re-default" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>'}function p(e){const t=Number(e.likes_count||0),s=Number(e.comments_count||0),a=e.my_reaction?f[e.my_reaction]:null;return`
    <div class="post-counts" ${t>0||s>0?"":"hidden"}>
      <span class="reaction-stack" data-reaction-stack>${$(e.top_reactions)}</span>
      <span data-likes-count>${t>0?t.toLocaleString():""}</span>
      <span class="comment-count" data-comment-count style="margin-left:auto">${s>0?`${s.toLocaleString()} comment${s===1?"":"s"}`:""}</span>
    </div>
    <div class="post-actions">
      <div class="reaction-wrap">
        ${T()}
        <button type="button" class="btn-like${a?" is-reacted":""}" data-act="like"
                data-reaction="${a?a.key:""}" aria-pressed="${a?"true":"false"}">
          <span class="re-emoji">${a?a.emoji:""}</span>
          ${A()}
          <span data-like-label class="lbl" ${a?`style="color:${a.color}"`:""}>${a?a.label:"Like"}</span>
        </button>
      </div>
      <button type="button" class="btn-comment" data-act="comment">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span class="lbl">Comment</span>
      </button>
      <button type="button" class="btn-share" data-act="share">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        <span class="lbl">Share</span>
      </button>
    </div>
  `}function V(e,t,s){if(!t)return;const a=s?f[s]:null;t.classList.toggle("is-reacted",!!a),t.setAttribute("aria-pressed",a?"true":"false"),t.dataset.reaction=a?a.key:"";const n=t.querySelector(".re-emoji");n&&(n.textContent=a?a.emoji:"");const o=t.querySelector("[data-like-label]");o&&(o.textContent=a?a.label:"Like",o.style.color=a?a.color:"")}function W(e,t){const s=e==null?void 0:e.querySelector("[data-likes-count]");s&&(s.textContent=t>0?Number(t).toLocaleString():""),H(e)}function G(e,t){const s=e==null?void 0:e.querySelector("[data-reaction-stack]");s&&(s.innerHTML=$(t))}function H(e){var n,o;const t=e==null?void 0:e.querySelector(".post-counts");if(!t)return;const s=(((n=t.querySelector("[data-likes-count]"))==null?void 0:n.textContent)||"").trim(),a=(((o=t.querySelector("[data-comment-count]"))==null?void 0:o.textContent)||"").trim();t.toggleAttribute("hidden",!s&&!a)}function N(e){e&&(e.classList.remove("is-open"),e.contains(document.activeElement)&&document.activeElement.blur(),e.classList.add("is-dismissed"),e.addEventListener("pointerleave",()=>e.classList.remove("is-dismissed"),{once:!0}))}function K(e,t){if(!e)return;let s=null;const a=()=>{s&&(clearTimeout(s),s=null)};e.addEventListener("click",n=>{const o=n.target.closest("[data-react]");if(!o||!e.contains(o))return;n.preventDefault(),n.stopPropagation();const r=o.closest("[data-post-id]");N(o.closest(".reaction-wrap")),t(r,o.dataset.react)}),e.addEventListener("pointerdown",n=>{if(n.pointerType==="mouse")return;const o=n.target.closest(".btn-like");if(!o)return;const r=o.closest(".reaction-wrap");s=setTimeout(()=>r==null?void 0:r.classList.add("is-open"),350)}),["pointerup","pointerleave","pointercancel"].forEach(n=>e.addEventListener(n,a)),document.addEventListener("click",n=>{n.target.closest(".reaction-wrap")||e.querySelectorAll(".reaction-wrap.is-open").forEach(o=>o.classList.remove("is-open"))})}function P(e){var o,r,l;const t=e.bg_color||"#EEF2FF",s=e.font_color||(L(t)?"#1E1B4B":"#FFFFFF"),a=(r=(o=e.media)==null?void 0:o[0])!=null&&r.url?`<div class="post-media"><img src="${i(e.media[0].url)}" loading="lazy" alt=""></div>`:"",n=((l=e.user)==null?void 0:l.username)==="robert_sheffield"?R():"";return`
    <article class="post-card status-card" data-post-id="${e.id}">
      ${u(e,"status","Status")}
      <div class="status-canvas" data-open style="background:${i(t)};color:${i(s)};">
        ${i(e.status_text||"")}
      </div>
      ${a}
      ${n}
      ${p(e)}
    </article>
  `}const q="https://www.effectivecpmnetwork.com/gc1v4hw8?key=b0e0c39593829879ba649d8cb2ef71ad",F=q;function R(){return`<a class="newsbot-continue" href="${F}" target="_blank" rel="noopener sponsored">Continue reading…</a>`}function Y(e=document){e.querySelectorAll("[data-countdown]:not([data-cd-bound])").forEach(s=>{s.dataset.cdBound="1";const a=parseInt(s.dataset.countdown,10)||10,n=s.dataset.dl;if(!n)return;const o=s.querySelector("[data-cd-counter]"),r=s.querySelector("[data-cd-label]");let l=a;s.classList.add("is-counting"),s.classList.remove("is-ready"),s.disabled=!0,o&&(o.textContent=`${l}s`),r&&(r.textContent="Preparing your download…");const d=()=>{if(l-=1,l>0){o&&(o.textContent=`${l}s`),setTimeout(d,1e3);return}s.classList.remove("is-counting"),s.classList.add("is-ready"),s.disabled=!1,r&&(r.textContent="Get this book"),o&&(o.textContent="")};s.addEventListener("click",v=>{if(s.classList.contains("is-counting")){v.preventDefault();return}window.open(n,"_blank","noopener")}),setTimeout(d,1e3)})}const y="36ce0149ae6c36811ff6c54b088c483c",z=`https://pl23865704.effectivecpmnetwork.com/${y}/invoke.js`;function k(e){return`
    <div class="post-media adbot-slot" data-adbot-slot data-post-id="${e}">
      <div id="container-${y}"></div>
    </div>
  `}function Z(e){var a;if(!e)return;const t=(a=e.querySelectorAll)==null?void 0:a.call(e,"[data-adbot-slot]");if(!t||!t.length||document.getElementById("adbot-invoke"))return;const s=document.createElement("script");s.id="adbot-invoke",s.async=!0,s.dataset.cfasync="false",s.src=z,document.body.appendChild(s)}function J(){return`
    <article class="post-card image-card ad-feed-card" data-ad-feed>
      <div class="post-head">
        <span class="avatar ad-feed-av" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 12l9 4 9-4"/><path d="M3 17l9 4 9-4"/></svg>
        </span>
        <div class="who">
          <span class="name">Sponsored</span>
          <span class="meta"><span class="post-badge image">Ad</span></span>
        </div>
      </div>
      ${k("feed-top")}
    </article>
  `}function I(e){var v;const t=(e.media||[]).filter(c=>c==null?void 0:c.url);if(((v=e.user)==null?void 0:v.username)==="daniel_whitmore")return`
      <article class="post-card image-card adbot-card" data-post-id="${e.id}">
        ${u(e,"image","Sponsored")}
        ${k(e.id)}
        ${p(e)}
      </article>
    `;if(!t.length){const c=e.description?`<div class="post-body">${i(e.description)}</div>`:"";return`
      <article class="post-card image-card" data-post-id="${e.id}">
        ${u(e,"image","Photo")}
        ${c||'<div class="post-body" style="color:#94a3b8">[image unavailable]</div>'}
        ${p(e)}
      </article>
    `}const a=e.is_adult?'<span class="adult-pill">18+</span>':"",n=e.description?`<div class="post-body">${i(e.description)}</div>`:"",o=t.length>1,r=t.map(c=>`<img class="gallery-slide" src="${i(c.url)}" loading="lazy" alt="">`).join(""),l=o?`
      <button type="button" class="gallery-btn gallery-prev" data-gallery-nav="-1" aria-label="Previous image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <button type="button" class="gallery-btn gallery-next" data-gallery-nav="1" aria-label="Next image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
      <span class="gallery-counter" data-gallery-counter>1 / ${t.length}</span>
  `:"",d=o?`
    <div class="gallery-dots" data-gallery-dots>
      ${t.map((c,h)=>`<span class="dot${h===0?" active":""}"></span>`).join("")}
    </div>`:"";return`
    <article class="post-card image-card" data-post-id="${e.id}">
      ${u(e,"image","Photo")}
      ${n}
      <div class="post-media ${e.is_adult?"is-adult":""}">
        <div class="gallery-wrap">
          <div class="gallery-track${o?" is-multi":""}" data-gallery data-open>${r}</div>
          ${l}${a}
        </div>
      </div>
      ${d}
      ${p(e)}
    </article>
  `}function O(e){var n,o;const t=e.is_adult?'<span class="adult-pill">18+</span>':"",s=e.thumbnail||((o=(n=e.media)==null?void 0:n[0])==null?void 0:o.url)||"",a=e.description?`<div class="post-body">${i(e.description)}</div>`:"";return s?`
    <article class="post-card video-card" data-post-id="${e.id}">
      ${u(e,"video","Video")}
      ${a}
      <div class="video-thumb-wrap ${e.is_adult?"is-adult":""}" data-open>
        <img src="${i(s)}" loading="lazy" alt="">
        <div class="play-btn"><span class="play-disc"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></div>
        ${t}
      </div>
      ${p(e)}
    </article>
  `:`
      <article class="post-card video-card" data-post-id="${e.id}">
        ${u(e,"video","Video")}
        ${a||'<div class="post-body" style="color:#94a3b8">[video unavailable]</div>'}
        ${p(e)}
      </article>
    `}function U(e){var a;const t=(a=e.category)!=null&&a.name?`<span class="article-cat">${i(e.category.name)}</span>`:"",s=e.featured_image?`
    <a class="article-figure block" href="${i(e.view_url||"#")}">
      <img src="${i(e.featured_image)}" loading="lazy" alt="">
      ${t}
    </a>`:"";return`
    <article class="post-card article-card" data-post-id="${e.id}">
      ${u(e,"article","Article")}
      ${s}
      <div class="article-meta">
        <a href="${i(e.view_url||"#")}" class="article-title block hover:text-brand-600">${i(e.title||"Untitled")}</a>
        ${e.short_description?`<p class="article-desc">${i(e.short_description)}</p>`:""}
      </div>
      <a class="article-read" href="${i(e.view_url||"#")}">
        Read article
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      ${p(e)}
    </article>
  `}function D(e){const t=e.book||{},s=e.view_url||"#",a=t.cover_url?`<img src="${i(t.cover_url)}" alt="" referrerpolicy="no-referrer"
           onerror="this.replaceWith(Object.assign(document.createElement('span'), { className:'book-noimg', textContent:'No cover' }))">`:'<span class="book-noimg">No cover</span>',n=[t.extension?`<span class="book-tag ext">${i(t.extension)}</span>`:"",t.size?`<span class="book-tag size">${i(t.size)}</span>`:"",t.year?`<span class="book-tag year">📅 ${i(t.year)}</span>`:"",t.language?`<span class="book-tag lang">🌐 ${i(t.language)}</span>`:""].filter(Boolean).join("");return`
    <article class="post-card book-card" data-post-id="${e.id}" data-book-slug="${i(t.slug||"")}">
      ${u(e,"book","Book")}
      <div class="book-body">
        <a class="book-cover" href="${i(s)}">${a}</a>
        <div class="book-info">
          <a class="book-title" href="${i(s)}">${i(t.title||e.title||"Untitled")}</a>
          ${t.author?`<div class="book-author">by ${i(t.author)}</div>`:""}
          ${t.publisher?`<div class="book-pub">${i(t.publisher)}</div>`:""}
          <div class="book-tags">${n}</div>
        </div>
      </div>
      ${p(e)}
    </article>
  `}function Q(e){return e.type==="status"?P(e):e.type==="image"?I(e):e.type==="video"?O(e):e.type==="article"?U(e):e.type==="book"?D(e):""}function X(e){var o,r;const t=e.querySelector("[data-gallery]"),s=(r=(o=e.parentElement)==null?void 0:o.parentElement)==null?void 0:r.querySelector("[data-gallery-dots]"),a=e.querySelector("[data-gallery-counter]");if(!t)return;const n=()=>{if(!t.clientWidth)return;const l=Math.round(t.scrollLeft/t.clientWidth);s&&s.querySelectorAll(".dot").forEach((v,c)=>v.classList.toggle("active",c===l));const d=t.children.length;a&&(a.textContent=`${l+1} / ${d}`)};t.addEventListener("scroll",n,{passive:!0})}function ee(e,t){var o,r;if(!e||!t)return null;const s=t.embed_provider&&t.embed_id,a=(r=(o=t.media)==null?void 0:o[0])==null?void 0:r.url;if(s)e.innerHTML=`<div class="plyr__video-embed plyr-stage"
        data-plyr-provider="${i(t.embed_provider)}"
        data-plyr-embed-id="${i(t.embed_id)}"></div>`;else if(a)e.innerHTML=`<video class="plyr-stage" controls playsinline
        poster="${i(t.thumbnail||"")}">
        <source src="${i(t.media[0].url)}">
      </video>`;else return e.innerHTML="",null;const n=e.querySelector(".plyr-stage");return!n||typeof window.Plyr>"u"?null:new window.Plyr(n,{autoplay:!0,youtube:{noCookie:!0,rel:0,modestbranding:1}})}export{q as A,f as R,V as a,Y as b,X as c,K as d,Q as e,N as f,i as g,J as h,_ as i,Z as j,L as k,ee as m,R as n,G as r,W as s};
