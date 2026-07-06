const _=window.__APP__,g=[{key:"like",emoji:"👍",label:"Like",color:"#2563EB"},{key:"love",emoji:"❤️",label:"Love",color:"#F43F5E"},{key:"haha",emoji:"😆",label:"Haha",color:"#F59E0B"},{key:"wow",emoji:"😮",label:"Wow",color:"#F59E0B"},{key:"sad",emoji:"😢",label:"Sad",color:"#F59E0B"},{key:"angry",emoji:"😡",label:"Angry",color:"#EF4444"}],f=Object.fromEntries(g.map(t=>[t.key,t])),i=t=>String(t??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");function b(t){return t?`${_.urls.profileBase}/${encodeURIComponent(t)}`:"#"}function L(t){if(!t)return"";const e=Date.parse(t);if(Number.isNaN(e))return"";const s=Math.max(0,Date.now()-e),a=6e4,n=60*a,o=24*n;if(s>=5*o)return"";if(s<a)return"just now";if(s<n)return`${Math.floor(s/a)} min ago`;if(s<o)return`${Math.floor(s/n)} hr ago`;const r=Math.floor(s/o);return`${r} day${r===1?"":"s"} ago`}function x(t){if(!t||t[0]!=="#")return!0;const e=t.length===4?t.slice(1).split("").map(o=>o+o).join(""):t.slice(1,7),s=parseInt(e.slice(0,2),16),a=parseInt(e.slice(2,4),16),n=parseInt(e.slice(4,6),16);return(s*299+a*587+n*114)/1e3>=160}function C(t){const e=b(t==null?void 0:t.username),s=t!=null&&t.id?`data-user-id="${t.id}"`:"";if(t!=null&&t.profile_picture)return`<a href="${e}" ${s} data-user-link><img class="avatar" src="${i(t.profile_picture)}" alt=""></a>`;const a=i(((t==null?void 0:t.username)||(t==null?void 0:t.name)||"U").charAt(0).toUpperCase());return`<a href="${e}" ${s} data-user-link><span class="avatar">${a}</span></a>`}function u(t,e,s){const a=t.user||{},n=i(a.name||a.username||"User"),o=i(L(t.created_at_iso)),r=Number(t.views_count||0).toLocaleString(),l=b(a.username),d=a.id?`data-user-id="${a.id}"`:"";return`
    <div class="post-head">
      ${C(a)}
      <div class="who">
        <a href="${l}" ${d} data-user-link class="name hover:underline">${n}</a>
        <span class="meta">
          ${o?`<span>${o}</span><span class="dot"></span>`:""}
          <span class="post-badge ${e}">${s}</span>
          <span class="dot"></span>
          <span class="views" title="${r} views" data-card-views>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <strong data-views-count>${r}</strong>
            <span class="views-label">views</span>
          </span>
        </span>
      </div>
      ${M(t)}
    </div>
  `}function M(t){const e=!!t.is_owner,s=!!t.saved,a=[];return e||(a.push(m("not_interested","Not interested","Show fewer posts like this",S())),a.push(m("hide","Hide post","Remove from your feed",j()))),a.push(`
    <div class="post-menu-save-wrap ${s?"is-saved":""}" data-save-wrap>
      <button type="button" class="post-menu-item ${s?"is-active":""}"
              data-post-act="save" role="menuitem">
        <span class="pmi-ic">${E(s)}</span>
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
  `),e&&(a.push('<div class="post-menu-sep"></div>'),a.push(m("edit","Edit post","Update your post content",B())),a.push(m("delete","Delete post","Permanently remove this post",T(),"is-danger"))),`
    <div class="post-menu-wrap" data-post-menu>
      <button type="button" class="post-menu" aria-label="Post options" data-post-menu-trigger>
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
      </button>
      <div class="post-menu-panel" role="menu">
        ${a.join("")}
      </div>
    </div>
  `}function m(t,e,s,a,n=""){return`
    <button type="button" class="post-menu-item ${n}" data-post-act="${t}" role="menuitem">
      <span class="pmi-ic">${a}</span>
      <span class="pmi-text">
        <span class="pmi-title">${e}</span>
        <span class="pmi-sub">${s}</span>
      </span>
    </button>
  `}function S(){return'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>'}function j(){return'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a19.77 19.77 0 0 1 4.22-5.06"/><path d="M1 1l22 22"/><path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a19.5 19.5 0 0 1-3.13 4.18"/><path d="M14.12 14.12A3 3 0 1 1 9.88 9.88"/></svg>'}function E(t){return`<svg viewBox="0 0 24 24" fill="${t?"currentColor":"none"}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>`}function B(){return'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>'}function T(){return'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>'}function A(){return`
    <div class="reaction-pop" role="menu" aria-label="Pick a reaction">
      ${g.map(t=>`
        <button type="button" class="reaction-pop-btn" data-react="${t.key}" title="${t.label}" aria-label="${t.label}">
          <span class="re">${t.emoji}</span>
        </button>`).join("")}
    </div>`}function $(t){return(t||[]).slice(0,3).map(s=>{var a;return`<span class="re-chip">${((a=f[s])==null?void 0:a.emoji)||"👍"}</span>`}).join("")}function H(){return'<svg class="re-default" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>'}function p(t){const e=Number(t.likes_count||0),s=Number(t.comments_count||0),a=t.my_reaction?f[t.my_reaction]:null;return`
    <div class="post-counts" ${e>0||s>0?"":"hidden"}>
      <span class="reaction-stack" data-reaction-stack>${$(t.top_reactions)}</span>
      <span data-likes-count>${e>0?e.toLocaleString():""}</span>
      <span class="comment-count" data-comment-count style="margin-left:auto">${s>0?`${s.toLocaleString()} comment${s===1?"":"s"}`:""}</span>
    </div>
    <div class="post-actions">
      <div class="reaction-wrap">
        ${A()}
        <button type="button" class="btn-like${a?" is-reacted":""}" data-act="like"
                data-reaction="${a?a.key:""}" aria-pressed="${a?"true":"false"}">
          <span class="re-emoji">${a?a.emoji:""}</span>
          ${H()}
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
  `}function V(t,e,s){if(!e)return;const a=s?f[s]:null;e.classList.toggle("is-reacted",!!a),e.setAttribute("aria-pressed",a?"true":"false"),e.dataset.reaction=a?a.key:"";const n=e.querySelector(".re-emoji");n&&(n.textContent=a?a.emoji:"");const o=e.querySelector("[data-like-label]");o&&(o.textContent=a?a.label:"Like",o.style.color=a?a.color:"")}function W(t,e){const s=t==null?void 0:t.querySelector("[data-likes-count]");s&&(s.textContent=e>0?Number(e).toLocaleString():""),P(t)}function G(t,e){const s=t==null?void 0:t.querySelector("[data-reaction-stack]");s&&(s.innerHTML=$(e))}function P(t){var n,o;const e=t==null?void 0:t.querySelector(".post-counts");if(!e)return;const s=(((n=e.querySelector("[data-likes-count]"))==null?void 0:n.textContent)||"").trim(),a=(((o=e.querySelector("[data-comment-count]"))==null?void 0:o.textContent)||"").trim();e.toggleAttribute("hidden",!s&&!a)}function N(t){t&&(t.classList.remove("is-open"),t.contains(document.activeElement)&&document.activeElement.blur(),t.classList.add("is-dismissed"),t.addEventListener("pointerleave",()=>t.classList.remove("is-dismissed"),{once:!0}))}function K(t,e){if(!t)return;let s=null;const a=()=>{s&&(clearTimeout(s),s=null)};t.addEventListener("click",n=>{const o=n.target.closest("[data-react]");if(!o||!t.contains(o))return;n.preventDefault(),n.stopPropagation();const r=o.closest("[data-post-id]");N(o.closest(".reaction-wrap")),e(r,o.dataset.react)}),t.addEventListener("pointerdown",n=>{if(n.pointerType==="mouse")return;const o=n.target.closest(".btn-like");if(!o)return;const r=o.closest(".reaction-wrap");s=setTimeout(()=>r==null?void 0:r.classList.add("is-open"),350)}),["pointerup","pointerleave","pointercancel"].forEach(n=>t.addEventListener(n,a)),document.addEventListener("click",n=>{n.target.closest(".reaction-wrap")||t.querySelectorAll(".reaction-wrap.is-open").forEach(o=>o.classList.remove("is-open"))})}function q(t){var o,r,l;const e=t.bg_color||"#EEF2FF",s=t.font_color||(x(e)?"#1E1B4B":"#FFFFFF"),a=(r=(o=t.media)==null?void 0:o[0])!=null&&r.url?`<div class="post-media"><img src="${i(t.media[0].url)}" loading="lazy" alt=""></div>`:"",n=((l=t.user)==null?void 0:l.username)==="robert_sheffield"?R():"";return`
    <article class="post-card status-card" data-post-id="${t.id}">
      ${u(t,"status","Status")}
      <div class="status-canvas" data-open style="background:${i(e)};color:${i(s)};">
        ${i(t.status_text||"")}
      </div>
      ${a}
      ${n}
      ${p(t)}
    </article>
  `}const y="https://www.effectivecpmnetwork.com/gc1v4hw8?key=b0e0c39593829879ba649d8cb2ef71ad",F=y;function R(){return`<a class="newsbot-continue" href="${F}" target="_blank" rel="noopener sponsored">Continue reading…</a>`}function Y(t=document){t.querySelectorAll("[data-countdown]:not([data-cd-bound])").forEach(s=>{s.dataset.cdBound="1";const a=parseInt(s.dataset.countdown,10)||10,n=s.dataset.dl;if(!n)return;const o=s.querySelector("[data-cd-counter]"),r=s.querySelector("[data-cd-label]");let l=a;s.classList.add("is-counting"),s.classList.remove("is-ready"),s.disabled=!0,o&&(o.textContent=`${l}s`),r&&(r.textContent="Preparing your download…");const d=()=>{if(l-=1,l>0){o&&(o.textContent=`${l}s`),setTimeout(d,1e3);return}s.classList.remove("is-counting"),s.classList.add("is-ready"),s.disabled=!1,r&&(r.textContent="Get this book"),o&&(o.textContent="")};s.addEventListener("click",v=>{if(s.classList.contains("is-counting")){v.preventDefault();return}window.open(n,"_blank","noopener")}),setTimeout(d,1e3)})}const k="36ce0149ae6c36811ff6c54b088c483c",z=`https://pl23865704.effectivecpmnetwork.com/${k}/invoke.js`;function h(t){return`
    <div class="post-media adbot-slot" data-adbot-slot data-post-id="${t}">
      <div id="container-${k}"></div>
    </div>
  `}function Z(t){var a;if(!t)return;const e=(a=t.querySelectorAll)==null?void 0:a.call(t,"[data-adbot-slot]");if(!e||!e.length||document.getElementById("adbot-invoke"))return;const s=document.createElement("script");s.id="adbot-invoke",s.async=!0,s.dataset.cfasync="false",s.src=z,document.body.appendChild(s)}function J(){return`
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
      ${h("feed-top")}
    </article>
  `}function I(t){var v;const e=(t.media||[]).filter(c=>c==null?void 0:c.url);if(((v=t.user)==null?void 0:v.username)==="daniel_whitmore")return`
      <article class="post-card image-card adbot-card" data-post-id="${t.id}">
        ${u(t,"image","Sponsored")}
        ${h(t.id)}
        ${p(t)}
      </article>
    `;if(!e.length){const c=t.description?`<div class="post-body">${i(t.description)}</div>`:"";return`
      <article class="post-card image-card" data-post-id="${t.id}">
        ${u(t,"image","Photo")}
        ${c||'<div class="post-body" style="color:#94a3b8">[image unavailable]</div>'}
        ${p(t)}
      </article>
    `}const a=t.is_adult?'<span class="adult-pill">18+</span>':"",n=t.description?`<div class="post-body">${i(t.description)}</div>`:"",o=e.length>1,r=e.map(c=>`<img class="gallery-slide" src="${i(c.url)}" loading="lazy" alt="">`).join(""),l=o?`
      <button type="button" class="gallery-btn gallery-prev" data-gallery-nav="-1" aria-label="Previous image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <button type="button" class="gallery-btn gallery-next" data-gallery-nav="1" aria-label="Next image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
      <span class="gallery-counter" data-gallery-counter>1 / ${e.length}</span>
  `:"",d=o?`
    <div class="gallery-dots" data-gallery-dots>
      ${e.map((c,w)=>`<span class="dot${w===0?" active":""}"></span>`).join("")}
    </div>`:"";return`
    <article class="post-card image-card" data-post-id="${t.id}">
      ${u(t,"image","Photo")}
      ${n}
      <div class="post-media ${t.is_adult?"is-adult":""}">
        <div class="gallery-wrap">
          <div class="gallery-track${o?" is-multi":""}" data-gallery data-open>${r}</div>
          ${l}${a}
        </div>
      </div>
      ${d}
      ${p(t)}
    </article>
  `}function O(t){var n,o;const e=t.is_adult?'<span class="adult-pill">18+</span>':"",s=t.thumbnail||((o=(n=t.media)==null?void 0:n[0])==null?void 0:o.url)||"",a=t.description?`<div class="post-body">${i(t.description)}</div>`:"";return s?`
    <article class="post-card video-card" data-post-id="${t.id}">
      ${u(t,"video","Video")}
      ${a}
      <div class="video-thumb-wrap ${t.is_adult?"is-adult":""}" data-open>
        <img src="${i(s)}" loading="lazy" alt="">
        <div class="play-btn"><span class="play-disc"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></div>
        ${e}
      </div>
      ${p(t)}
    </article>
  `:`
      <article class="post-card video-card" data-post-id="${t.id}">
        ${u(t,"video","Video")}
        ${a||'<div class="post-body" style="color:#94a3b8">[video unavailable]</div>'}
        ${p(t)}
      </article>
    `}function U(t){var a;const e=(a=t.category)!=null&&a.name?`<span class="article-cat">${i(t.category.name)}</span>`:"",s=t.featured_image?`
    <a class="article-figure block" href="${i(t.view_url||"#")}">
      <img src="${i(t.featured_image)}" loading="lazy" alt="">
      ${e}
    </a>`:"";return`
    <article class="post-card article-card" data-post-id="${t.id}">
      ${u(t,"article","Article")}
      ${s}
      <div class="article-meta">
        <a href="${i(t.view_url||"#")}" class="article-title block hover:text-brand-600">${i(t.title||"Untitled")}</a>
        ${t.short_description?`<p class="article-desc">${i(t.short_description)}</p>`:""}
      </div>
      <a class="article-read" href="${i(t.view_url||"#")}">
        Read article
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      ${p(t)}
    </article>
  `}function D(t){const e=t.book||{},s=t.view_url||"#",a=e.cover_url?`<img src="${i(e.cover_url)}" alt="" referrerpolicy="no-referrer"
           onerror="this.replaceWith(Object.assign(document.createElement('span'), { className:'book-noimg', textContent:'No cover' }))">`:'<span class="book-noimg">No cover</span>',n=[e.extension?`<span class="book-tag ext">${i(e.extension)}</span>`:"",e.size?`<span class="book-tag size">${i(e.size)}</span>`:"",e.year?`<span class="book-tag year">📅 ${i(e.year)}</span>`:"",e.language?`<span class="book-tag lang">🌐 ${i(e.language)}</span>`:""].filter(Boolean).join(""),o=e.download_url?`<button type="button" class="book-dl is-counting"
              data-countdown="10" data-dl="${i(e.download_url)}">
         <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
         <span data-cd-label>Preparing your download…</span>
         <span class="book-dl-counter" data-cd-counter>10s</span>
       </button>`:"",r=e.description?`<p class="book-desc">${i(String(e.description).slice(0,220))}${String(e.description).length>220?"…":""}</p>`:"";return`
    <article class="post-card book-card" data-post-id="${t.id}" data-book-slug="${i(e.slug||"")}">
      ${u(t,"book","Book")}
      <div class="book-body">
        <a class="book-cover" href="${y}" target="_blank" rel="sponsored noopener" data-ad-cover>${a}</a>
        <div class="book-info">
          <a class="book-title" href="${i(s)}">${i(e.title||t.title||"Untitled")}</a>
          ${e.author?`<div class="book-author">by ${i(e.author)}</div>`:""}
          ${e.publisher?`<div class="book-pub">${i(e.publisher)}</div>`:""}
          <div class="book-tags">${n}</div>
          ${r}
          ${o}
        </div>
      </div>
      ${p(t)}
    </article>
  `}function Q(t){return t.type==="status"?q(t):t.type==="image"?I(t):t.type==="video"?O(t):t.type==="article"?U(t):t.type==="book"?D(t):""}function X(t){var o,r;const e=t.querySelector("[data-gallery]"),s=(r=(o=t.parentElement)==null?void 0:o.parentElement)==null?void 0:r.querySelector("[data-gallery-dots]"),a=t.querySelector("[data-gallery-counter]");if(!e)return;const n=()=>{if(!e.clientWidth)return;const l=Math.round(e.scrollLeft/e.clientWidth);s&&s.querySelectorAll(".dot").forEach((v,c)=>v.classList.toggle("active",c===l));const d=e.children.length;a&&(a.textContent=`${l+1} / ${d}`)};e.addEventListener("scroll",n,{passive:!0})}function tt(t,e){var o,r;if(!t||!e)return null;const s=e.embed_provider&&e.embed_id,a=(r=(o=e.media)==null?void 0:o[0])==null?void 0:r.url;if(s)t.innerHTML=`<div class="plyr__video-embed plyr-stage"
        data-plyr-provider="${i(e.embed_provider)}"
        data-plyr-embed-id="${i(e.embed_id)}"></div>`;else if(a)t.innerHTML=`<video class="plyr-stage" controls playsinline
        poster="${i(e.thumbnail||"")}">
        <source src="${i(e.media[0].url)}">
      </video>`;else return t.innerHTML="",null;const n=t.querySelector(".plyr-stage");return!n||typeof window.Plyr>"u"?null:new window.Plyr(n,{autoplay:!0,youtube:{noCookie:!0,rel:0,modestbranding:1}})}export{y as A,f as R,V as a,Y as b,X as c,K as d,Q as e,N as f,i as g,J as h,L as i,Z as j,x as k,tt as m,R as n,G as r,W as s};
