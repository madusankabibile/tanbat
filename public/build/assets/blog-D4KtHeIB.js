const k=window.__BLOG__||{urls:{}},L=window.Tanbat.api,r=(e,t=document)=>t.querySelector(e),b=(e,t=document)=>Array.from(t.querySelectorAll(e)),o=e=>String(e??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;"),s={sort:"for-you",q:"",category:k.initialCategory||"all",page:1,lastPage:1,total:0,loading:!1},w={"for-you":"Picked for you",hot:"Hottest articles",new:"Freshly published"};function m(e){return String(e||"?").trim().split(/\s+/).slice(0,2).map(t=>t[0]||"").join("").toUpperCase()||"?"}function p(e){return e=Number(e)||0,e>=1e6?(e/1e6).toFixed(e>=1e7?0:1).replace(/\.0$/,"")+"M":e>=1e3?(e/1e3).toFixed(e>=1e4?0:1).replace(/\.0$/,"")+"K":String(e)}function x(e){const t=[];if(e.is_local&&e.country){const n=`${p(e.country_views)} reads in ${o(e.country)}`;t.push(`<span class="bc-badge local" title="${n}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>Popular in ${o(e.country)}</span>`)}return e.is_hot&&t.push('<span class="bc-badge hot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5Z"/></svg>Hot</span>'),e.is_new&&t.push('<span class="bc-badge new">New</span>'),t.length?`<div class="bc-badges">${t.join("")}</div>`:""}function M(e){var d,g,h,f,v;const t=o(e.view_url||"#"),n=e.featured_image?`<img src="${o(e.featured_image)}" loading="lazy" alt="" referrerpolicy="no-referrer"
          onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'bc-noimg',innerHTML:'<svg viewBox=\\'0 0 24 24\\' fill=\\'none\\' stroke=\\'currentColor\\' stroke-width=\\'1.6\\'><path d=\\'M4 5h16v14H4z\\'/><path d=\\'m4 15 5-5 4 4 3-3 4 4\\'/></svg>'}))">`:'<span class="bc-noimg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 5h16v14H4z"/><path d="m4 15 5-5 4 4 3-3 4 4"/></svg></span>',l=e.category?`<span class="bc-cat">${o(e.category)}</span>`:"",c=(d=e.author)!=null&&d.avatar?`<img src="${o(e.author.avatar)}" alt="" referrerpolicy="no-referrer" onerror="this.replaceWith(document.createTextNode('${o(m((g=e.author)==null?void 0:g.name))}'))">`:o(m((h=e.author)==null?void 0:h.name)),i=(f=e.author)!=null&&f.name?o(e.author.name):"Tanbat",a=(v=e.author)!=null&&v.url?o(e.author.url):"#";return`
    <article class="blog-card" data-id="${e.id}">
      <a class="bc-figure" href="${t}">
        ${x(e)}
        ${n}
        ${l}
      </a>
      <div class="bc-body">
        <a class="bc-title" href="${t}">${o(e.title||"Untitled")}</a>
        ${e.excerpt?`<p class="bc-excerpt">${o(e.excerpt)}</p>`:'<p class="bc-excerpt"></p>'}
        <div class="bc-foot">
          <a class="bc-av" href="${a}" aria-label="${i}">${c}</a>
          <a class="bc-who" href="${a}">
            <span class="bc-author">${i}</span>
            <span class="bc-sub">${o(e.published_at||"")}</span>
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
    </div>`).join("")}async function u({reset:e=!1}={}){if(s.loading)return;s.loading=!0;const t=r("#blogGrid"),n=r("#blogLoading"),l=r("#blogEmpty"),c=r("#blogPager"),i=r("#blogResultNote");e&&(s.page=1,t.innerHTML=E(),l.classList.add("hidden"),c.classList.add("hidden")),n.classList.remove("hidden");try{const a=new URLSearchParams({sort:s.sort,page:String(s.page)});s.q&&a.set("q",s.q),s.category&&s.category!=="all"&&a.set("category",s.category);const d=await L(`${k.urls.feed}?${a.toString()}`),g=d.items||[];if(s.lastPage=d.last_page||1,s.total=d.total||0,e&&(t.innerHTML=""),s.page===1&&!g.length)l.classList.remove("hidden"),c.classList.add("hidden"),i.textContent="";else{t.insertAdjacentHTML("beforeend",g.map(M).join("")),c.classList.toggle("hidden",!d.has_more);const h=w[s.sort]||"Articles";i.textContent=s.q?`${p(s.total)} result${s.total===1?"":"s"} for “${s.q}”`:`${h} · ${p(s.total)} articles`}}catch{e&&(t.innerHTML=""),r("#blogEmpty").classList.remove("hidden"),r("#blogEmpty .be-title").textContent="Could not load articles",r("#blogEmpty .be-sub").textContent="Please check your connection and try again."}finally{n.classList.add("hidden"),s.loading=!1}}function y(e){s.sort!==e&&(s.sort=e,b(".blog-tab").forEach(t=>t.classList.toggle("is-active",t.dataset.sort===e)),b(".blog-navlink").forEach(t=>t.classList.toggle("is-active",t.dataset.sort===e)),u({reset:!0}))}function T(e,t){let n;return(...l)=>{clearTimeout(n),n=setTimeout(()=>e(...l),t)}}function $(){var n,l,c;b(".blog-navlink").forEach(i=>i.classList.toggle("is-active",i.dataset.sort===s.sort)),(n=r("#blogTabs"))==null||n.addEventListener("click",i=>{const a=i.target.closest(".blog-tab");a&&y(a.dataset.sort)}),(l=r(".blog-left"))==null||l.addEventListener("click",i=>{const a=i.target.closest(".blog-navlink");a&&y(a.dataset.sort)});const e=r("#blogSearchInput"),t=r("#blogSearchClear");e==null||e.addEventListener("input",T(()=>{s.q=e.value.trim(),t.classList.toggle("hidden",!e.value),u({reset:!0})},350)),t==null||t.addEventListener("click",()=>{e.value="",s.q="",t.classList.add("hidden"),e.focus(),u({reset:!0})}),(c=r("#blogLoadMore"))==null||c.addEventListener("click",()=>{s.page>=s.lastPage||(s.page+=1,u())}),u({reset:!0})}document.readyState==="loading"?document.addEventListener("DOMContentLoaded",$):$();
