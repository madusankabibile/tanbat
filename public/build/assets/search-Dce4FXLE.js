const y=window.__APP__,h=window.__SEARCH__||{q:"",tab:"all",urls:{}},d=(e,t=document)=>t.querySelector(e),g=(e,t=document)=>Array.from(t.querySelectorAll(e));var $;const L=(($=window.Tanbat)==null?void 0:$.toast)||(()=>{});var w;const _=(w=window.Tanbat)==null?void 0:w.api,a=e=>String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;"),u=e=>Number(e||0).toLocaleString();let o={q:h.q||"",tab:h.tab||"all",page:1,hasMore:!1};function x(){g(".search-tab").forEach(e=>{e.classList.toggle("active",e.dataset.tab===o.tab)})}function v(e){g(".search-count").forEach(t=>{const s=t.dataset.count;t.textContent=u((e==null?void 0:e[s])||0)})}function m(e){return e.view_url&&(e.type==="article"||e.type==="book")?e.view_url:`${y.urls.home}#post-${e.id}`}function k(e){var s;if(e.type==="book"&&((s=e.book)!=null&&s.cover_url))return e.book.cover_url;if(e.thumbnail)return e.thumbnail;if(e.featured_image)return e.featured_image;const t=(e.media||[])[0];return(t==null?void 0:t.url)||null}function M(e){if(e.type==="book")return S(e);const t=e.user||{},s=t.profile_picture?`<img class="h-9 w-9 rounded-full object-cover" src="${a(t.profile_picture)}" alt="">`:`<span class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white">${a((t.username||t.name||"U").charAt(0).toUpperCase())}</span>`,n=k(e),l=n?`<div class="relative h-32 w-32 shrink-0 overflow-hidden rounded-xl bg-slate-100 sm:h-36 sm:w-36">
         <img src="${a(n)}" class="h-full w-full object-cover" alt="" loading="lazy">
         ${e.type==="video"?'<span class="absolute inset-0 grid place-items-center bg-black/30 text-white"><svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor"><polygon points="6 4 20 12 6 20 6 4"/></svg></span>':""}
       </div>`:"",r=e.title||(e.status_text||e.description||e.short_description||"").slice(0,160),i=e.short_description||e.description||(e.title?"":e.status_text||"")||"";return`
    <a href="${a(m(e))}" class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-brand-200 hover:shadow-pop sm:flex-row">
      ${l}
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 text-xs text-slate-500">
          ${s}
          <span class="font-semibold text-slate-700">${a(t.name||t.username||"User")}</span>
          <span>·</span>
          <span class="capitalize">${a(e.type)}</span>
          ${e.category?`<span>·</span><span>${a(e.category.name)}</span>`:""}
          <span>·</span>
          <span>${a(e.created_at||"")}</span>
        </div>
        <h3 class="mt-2 line-clamp-2 text-base font-extrabold text-slate-900">${a(r||"(Untitled)")}</h3>
        ${i?`<p class="mt-1 line-clamp-2 text-sm text-slate-600">${a(i)}</p>`:""}
        <div class="mt-2 flex items-center gap-4 text-xs text-slate-500">
          <span>${u(e.likes_count)} likes</span>
          <span>${u(e.comments_count)} comments</span>
          <span>${u(e.views_count)} views</span>
        </div>
      </div>
    </a>`}function S(e){const t=e.book||{},s=t.cover_url?`<img src="${a(t.cover_url)}" class="h-full w-full object-cover" alt="" loading="lazy" referrerpolicy="no-referrer">`:'<span class="grid h-full w-full place-items-center text-xs text-slate-400 p-2 text-center">No cover</span>',n=[t.extension?`<span class="sb-tag ext">${a(t.extension)}</span>`:"",t.size?`<span class="sb-tag size">${a(t.size)}</span>`:"",t.year?`<span class="sb-tag year">${a(t.year)}</span>`:"",t.language?`<span class="sb-tag lang">${a(t.language)}</span>`:""].filter(Boolean).join(""),l=t.description?a(String(t.description).slice(0,180))+(String(t.description).length>180?"…":""):"";return`
    <a href="${a(m(e))}" class="search-book-card">
      <div class="sb-cover">${s}</div>
      <div class="sb-body">
        <div class="sb-meta-row">
          <span class="sb-badge">Book</span>
          <span class="sb-when">${a(e.created_at||"")}</span>
        </div>
        <h3 class="sb-title">${a(t.title||e.title||"Untitled")}</h3>
        ${t.author?`<div class="sb-author">by <strong>${a(t.author)}</strong></div>`:""}
        ${t.publisher?`<div class="sb-pub">${a(t.publisher)}</div>`:""}
        ${n?`<div class="sb-tags">${n}</div>`:""}
        ${l?`<p class="sb-blurb">${l}</p>`:""}
      </div>
    </a>`}function b(e,t){const s=[];return e.forEach(n=>{var l;if(t==="image")(n.media||[]).filter(r=>r.kind==="image").forEach(r=>{s.push(`
          <a href="${a(m(n))}" class="media-tile" title="${a(n.description||n.title||"")}">
            <img src="${a(r.url)}" alt="" loading="lazy">
            <span class="badge">Image</span>
          </a>`)});else{const r=n.thumbnail||((l=(n.media||[]).find(i=>i.kind==="image"))==null?void 0:l.url)||"";s.push(`
        <a href="${a(m(n))}" class="media-tile" title="${a(n.description||n.title||"")}">
          ${r?`<img src="${a(r)}" alt="" loading="lazy">`:""}
          <span class="badge">Video</span>
          <span class="play"><svg viewBox="0 0 24 24" fill="currentColor"><polygon points="6 4 20 12 6 20 6 4"/></svg></span>
        </a>`)}}),`<div class="media-grid">${s.join("")||'<div class="col-span-full py-8 text-center text-sm text-slate-500">No media found.</div>'}</div>`}function T(e){return e.length?`<div class="people-grid">${e.map(s=>{const n=s.profile_picture?`<img src="${a(s.profile_picture)}" alt="">`:`<span>${a((s.username||s.name||"U").charAt(0).toUpperCase())}</span>`;return`
      <a href="${a(s.url)}" class="person-card">
        <span class="pc-avatar">${n}</span>
        <div class="min-w-0 flex-1">
          <div class="pc-name truncate">${a(s.name||s.username)}</div>
          <div class="pc-handle truncate">&#64;${a(s.username)}</div>
          ${s.country?`<div class="pc-handle truncate">${a(s.country)}</div>`:""}
        </div>
      </a>`}).join("")}</div>`:""}function q(e){return e.length?`<div class="tag-grid">${e.map(s=>`
    <a href="${a(s.url)}" class="tag-card">
      <span class="tg-hash">#</span>
      <div class="min-w-0 flex-1">
        <div class="tg-name truncate">${a(s.name)}</div>
        <div class="tg-count">${u(s.count)} post${s.count===1?"":"s"}</div>
      </div>
    </a>`).join("")}</div>`:""}function E(e){return e.length?`<div class="space-y-3">${e.map(M).join("")}</div>`:'<div class="py-8 text-center text-sm text-slate-500">No posts match.</div>'}function H(e,{append:t=!1}={}){const s=d("#searchResults"),n=d("#searchEmpty"),l=d("#searchLoadMore");o.hasMore=!!e.has_more,l.classList.toggle("hidden",!o.hasMore),t||(s.innerHTML="");const r=e.tab||o.tab,i=e.items||[];let c="";r==="people"?c=T(i):r==="tags"?c=q(i):r==="images"?c=b(i,"image"):r==="videos"?c=b(i,"video"):c=E(i),t?s.insertAdjacentHTML("beforeend",c):s.innerHTML=c,s.children.length||s.querySelectorAll("a").length?n.classList.add("hidden"):n.classList.remove("hidden")}async function f({append:e=!1}={}){var s,n,l;if(!o.q){d("#searchEmpty").classList.add("hidden"),d("#searchResults").innerHTML="",v({posts:0,images:0,videos:0,people:0,tags:0});return}const t=d("#searchLoading");e||t.classList.remove("hidden");try{const r=new URL(h.urls.results,window.location.origin);r.searchParams.set("q",o.q),r.searchParams.set("tab",o.tab),r.searchParams.set("page",String(o.page));const i=await _(r.toString());v(i.counts||{}),H(i,{append:e});const c=d("#searchSummary");if(c){const p=(((s=i.counts)==null?void 0:s.posts)||0)+(((n=i.counts)==null?void 0:n.people)||0)+(((l=i.counts)==null?void 0:l.tags)||0);c.textContent=p?`Found ${u(p)} match${p===1?"":"es"} across posts, people and tags.`:`No matches found for "${o.q}".`}}catch(r){L(r.message||"Search failed","bad")}finally{t.classList.add("hidden")}}function A(){const e=new URL(window.location.href);o.q?e.searchParams.set("q",o.q):e.searchParams.delete("q"),e.searchParams.set("tab",o.tab),window.history.replaceState(null,"",e.toString())}function C(){g(".search-tab").forEach(e=>{e.addEventListener("click",()=>{e.dataset.tab!==o.tab&&(o.tab=e.dataset.tab,o.page=1,x(),A(),f({append:!1}),window.scrollTo({top:0,behavior:"smooth"}))})})}function P(){var e;(e=d("#searchLoadMore"))==null||e.addEventListener("click",()=>{o.page+=1,f({append:!0})})}function U(){const e=document.getElementById("navSearchInput");e&&e.addEventListener("input",()=>{e.value.trim()})}document.addEventListener("DOMContentLoaded",()=>{x(),C(),P(),U(),f({append:!1})});
