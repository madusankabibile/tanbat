const k=window.__BLOG__||{urls:{}},L=window.Tanbat.api,o=(e,t=document)=>t.querySelector(e),b=(e,t=document)=>Array.from(t.querySelectorAll(e)),a=e=>String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;"),s={sort:"for-you",q:"",category:"all",page:1,lastPage:1,total:0,loading:!1},w={"for-you":"Picked for you",hot:"Hottest articles",new:"Freshly published"};function m(e){return String(e||"?").trim().split(/\s+/).slice(0,2).map(t=>t[0]||"").join("").toUpperCase()||"?"}function p(e){return e=Number(e)||0,e>=1e6?(e/1e6).toFixed(e>=1e7?0:1).replace(/\.0$/,"")+"M":e>=1e3?(e/1e3).toFixed(e>=1e4?0:1).replace(/\.0$/,"")+"K":String(e)}function x(e){const t=[];if(e.is_local&&e.country){const r=`${p(e.country_views)} reads in ${a(e.country)}`;t.push(`<span class="bc-badge local" title="${r}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>Popular in ${a(e.country)}</span>`)}return e.is_hot&&t.push('<span class="bc-badge hot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5Z"/></svg>Hot</span>'),e.is_new&&t.push('<span class="bc-badge new">New</span>'),t.length?`<div class="bc-badges">${t.join("")}</div>`:""}function M(e){var d,g,h,f,v;const t=a(e.view_url||"#"),r=e.featured_image?`<img src="${a(e.featured_image)}" loading="lazy" alt="" referrerpolicy="no-referrer"
          onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'bc-noimg',innerHTML:'<svg viewBox=\\'0 0 24 24\\' fill=\\'none\\' stroke=\\'currentColor\\' stroke-width=\\'1.6\\'><path d=\\'M4 5h16v14H4z\\'/><path d=\\'m4 15 5-5 4 4 3-3 4 4\\'/></svg>'}))">`:'<span class="bc-noimg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 5h16v14H4z"/><path d="m4 15 5-5 4 4 3-3 4 4"/></svg></span>',l=e.category?`<span class="bc-cat">${a(e.category)}</span>`:"",c=(d=e.author)!=null&&d.avatar?`<img src="${a(e.author.avatar)}" alt="" referrerpolicy="no-referrer" onerror="this.replaceWith(document.createTextNode('${a(m((g=e.author)==null?void 0:g.name))}'))">`:a(m((h=e.author)==null?void 0:h.name)),n=(f=e.author)!=null&&f.name?a(e.author.name):"Tanbat",i=(v=e.author)!=null&&v.url?a(e.author.url):"#";return`
    <article class="blog-card" data-id="${e.id}">
      <a class="bc-figure" href="${t}">
        ${x(e)}
        ${r}
        ${l}
      </a>
      <div class="bc-body">
        <a class="bc-title" href="${t}">${a(e.title||"Untitled")}</a>
        ${e.excerpt?`<p class="bc-excerpt">${a(e.excerpt)}</p>`:'<p class="bc-excerpt"></p>'}
        <div class="bc-foot">
          <a class="bc-av" href="${i}" aria-label="${n}">${c}</a>
          <a class="bc-who" href="${i}">
            <span class="bc-author">${n}</span>
            <span class="bc-sub">${a(e.published_at||"")}</span>
          </a>
          <span class="bc-views" title="${p(e.views)} views">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            ${p(e.views)}
          </span>
        </div>
      </div>
    </article>
  `}function E(e=6){return Array.from({length:e}).map(()=>`
    <div class="bc-skeleton">
      <span class="sk sk-img"></span>
      <div class="sk-pad">
        <span class="sk sk-line" style="width:90%"></span>
        <span class="sk sk-line" style="width:70%"></span>
        <span class="sk sk-line" style="width:40%;margin-top:14px"></span>
      </div>
    </div>`).join("")}async function u({reset:e=!1}={}){if(s.loading)return;s.loading=!0;const t=o("#blogGrid"),r=o("#blogLoading"),l=o("#blogEmpty"),c=o("#blogPager"),n=o("#blogResultNote");e&&(s.page=1,t.innerHTML=E(),l.classList.add("hidden"),c.classList.add("hidden")),r.classList.remove("hidden");try{const i=new URLSearchParams({sort:s.sort,page:String(s.page)});s.q&&i.set("q",s.q),s.category&&s.category;const d=await L(`${k.urls.feed}?${i.toString()}`),g=d.items||[];if(s.lastPage=d.last_page||1,s.total=d.total||0,e&&(t.innerHTML=""),s.page===1&&!g.length)l.classList.remove("hidden"),c.classList.add("hidden"),n.textContent="";else{t.insertAdjacentHTML("beforeend",g.map(M).join("")),c.classList.toggle("hidden",!d.has_more);const h=w[s.sort]||"Articles";n.textContent=s.q?`${p(s.total)} result${s.total===1?"":"s"} for “${s.q}”`:`${h} · ${p(s.total)} articles`}}catch{e&&(t.innerHTML=""),o("#blogEmpty").classList.remove("hidden"),o("#blogEmpty .be-title").textContent="Could not load articles",o("#blogEmpty .be-sub").textContent="Please check your connection and try again."}finally{r.classList.add("hidden"),s.loading=!1}}function $(e){s.sort!==e&&(s.sort=e,b(".blog-tab").forEach(t=>t.classList.toggle("is-active",t.dataset.sort===e)),b(".blog-navlink").forEach(t=>t.classList.toggle("is-active",t.dataset.sort===e)),u({reset:!0}))}function T(e,t){let r;return(...l)=>{clearTimeout(r),r=setTimeout(()=>e(...l),t)}}function y(){var r,l,c;b(".blog-navlink").forEach(n=>n.classList.toggle("is-active",n.dataset.sort===s.sort)),(r=o("#blogTabs"))==null||r.addEventListener("click",n=>{const i=n.target.closest(".blog-tab");i&&$(i.dataset.sort)}),(l=o(".blog-left"))==null||l.addEventListener("click",n=>{const i=n.target.closest(".blog-navlink");i&&$(i.dataset.sort)});const e=o("#blogSearchInput"),t=o("#blogSearchClear");e==null||e.addEventListener("input",T(()=>{s.q=e.value.trim(),t.classList.toggle("hidden",!e.value),u({reset:!0})},350)),t==null||t.addEventListener("click",()=>{e.value="",s.q="",t.classList.add("hidden"),e.focus(),u({reset:!0})}),(c=o("#blogLoadMore"))==null||c.addEventListener("click",()=>{s.page>=s.lastPage||(s.page+=1,u())}),u({reset:!0})}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",y):y();
