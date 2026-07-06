import{b as g}from"./cards-Cz8ZBmoO.js";const d=window.__APP__,k=window.Tanbat.api,u=window.Tanbat.toast,h={like:"👍",love:"❤️",haha:"😆",wow:"😮",sad:"😢",angry:"😡"},w={like:"Like",love:"Love",haha:"Haha",wow:"Wow",sad:"Sad",angry:"Angry"},r=(t,n=document)=>n.querySelector(t),$=(t,n=document)=>Array.from(n.querySelectorAll(t)),l=t=>String(t??"").replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");function p(){var t;return(t=document.querySelector("[data-post-id]"))==null?void 0:t.dataset.postId}function C(t,n){const o=r("#bkLikeBtn");if(!o)return;o.classList.toggle("is-active",!!t),o.dataset.reaction=t||"";const e=o.querySelector(".re-emoji");e&&(e.textContent=t?h[t]:"");const a=o.querySelector(".re-label");if(a&&(a.textContent=t?w[t]:"Like"),typeof n=="number"){const s=r("#bkLikeCount");s&&(s.textContent=n.toLocaleString())}}async function f(t){if(!d.user){u("Sign in to react.","bad");return}const n=p();if(!n)return;const o=r(".reaction-wrap");o&&(o.classList.add("is-dismissed"),o.addEventListener("pointerleave",()=>o.classList.remove("is-dismissed"),{once:!0}));try{const e=await k(`${d.urls.api.posts}/${n}/like`,{method:"POST",body:{reaction:t}});C(e.reaction,e.likes_count)}catch(e){u(e.message||"Could not update reaction.","bad")}}function S(){var o;(o=r("#bkLikeBtn"))==null||o.addEventListener("click",e=>{e.preventDefault(),e.stopPropagation();const a=r("#bkLikeBtn").dataset.reaction;f(a||"like")}),$(".reaction-pop-btn").forEach(e=>{e.addEventListener("click",a=>{a.preventDefault(),a.stopPropagation(),f(e.dataset.react)})});let t=null;const n=r("#bkLikeBtn");n==null||n.addEventListener("pointerdown",e=>{e.pointerType!=="mouse"&&(t=setTimeout(()=>{var a;return(a=r(".reaction-wrap"))==null?void 0:a.classList.add("is-open")},350))}),["pointerup","pointerleave","pointercancel"].forEach(e=>n==null?void 0:n.addEventListener(e,()=>{t&&(clearTimeout(t),t=null)})),document.addEventListener("click",e=>{var a;e.target.closest(".reaction-wrap")||(a=r(".reaction-wrap"))==null||a.classList.remove("is-open")})}function E(){var t;(t=r("#bkShareBtn"))==null||t.addEventListener("click",()=>{var s,c,i,b,m;const n=p(),o=location.href,e=((c=(s=document.querySelector(".bk-title"))==null?void 0:s.textContent)==null?void 0:c.trim())||"A book on Tanbat",a=((i=document.querySelector(".bk-hero-cover img"))==null?void 0:i.src)||"";(m=(b=window.Tanbat)==null?void 0:b.openShare)==null||m.call(b,{kind:"post",postId:n,url:o,title:e,image:a})})}function v(t){[".bk-comments-count","#bkCommentCount"].forEach(n=>{const o=document.querySelector(n);if(!o)return;const e=(o.textContent||"").replace(/[^\d]/g,""),a=Math.max(0,(parseInt(e,10)||0)+t);o.textContent=o.classList.contains("bk-comments-count")?`(${a.toLocaleString()})`:a.toLocaleString()})}function y(t,n=!1){const o="bk-av"+(n?" bk-av-sm":"");if(t!=null&&t.profile_picture)return`<img src="${l(t.profile_picture)}" class="${o}" alt="">`;const e=l(((t==null?void 0:t.username)||(t==null?void 0:t.name)||"U").charAt(0).toUpperCase());return`<span class="${o} bk-av-fallback">${e}</span>`}function q(t){var n,o,e;return`
    <div class="bk-thread" data-comment-id="${t.id}">
      <div class="bk-thread-row">
        ${y(t.user)}
        <div class="bk-thread-body">
          <div class="bk-bubble">
            <div class="bk-bubble-head">
              <a href="${l((d.urls.profileBase||"/u")+"/"+(((n=t.user)==null?void 0:n.username)||""))}" class="bk-bubble-name">${l(((o=t.user)==null?void 0:o.username)||"User")}</a>
              <span class="bk-bubble-when">${l(t.created_at||"just now")}</span>
            </div>
            <p class="bk-bubble-text">${l(t.body||"")}</p>
          </div>
          ${d.user?`
            <div class="bk-bubble-actions">
              <button type="button" class="bk-reply-trigger" data-parent="${t.id}">Reply</button>
            </div>
            <form class="bk-reply-form" data-parent="${t.id}" hidden>
              <input type="text" maxlength="1000" required placeholder="Reply to @${l(((e=t.user)==null?void 0:e.username)||"")}…">
              <button type="submit">Reply</button>
              <button type="button" class="bk-reply-cancel">Cancel</button>
            </form>
          `:""}
          <div class="bk-replies" data-replies-of="${t.id}"></div>
        </div>
      </div>
    </div>`}function T(t){var n;return`
    <div class="bk-reply" data-reply-id="${t.id}">
      ${y(t.user,!0)}
      <div class="bk-bubble bk-bubble-reply">
        <div class="bk-bubble-head">
          <span class="bk-bubble-name">${l(((n=t.user)==null?void 0:n.username)||"User")}</span>
          <span class="bk-bubble-when">${l(t.created_at||"just now")}</span>
        </div>
        <p class="bk-bubble-text">${l(t.body||"")}</p>
      </div>
    </div>`}async function L(t,n){const o=p();if(!o)return null;const e=await k(`${d.urls.api.posts}/${o}/comments`,{method:"POST",body:{body:t,parent_id:n||null}});return(e==null?void 0:e.comment)||null}function x(){const t=r("#bkCommentForm");t&&t.addEventListener("submit",async n=>{var s,c;n.preventDefault();const o=r("#bkCommentBody"),e=((o==null?void 0:o.value)||"").trim();if(!e)return;const a=t.querySelector(".bk-comment-submit");a&&(a.disabled=!0);try{const i=await L(e,null);i&&((s=r(".bk-comments-empty"))==null||s.remove(),(c=r("#bkCommentList"))==null||c.insertAdjacentHTML("afterbegin",q(i)),o.value="",v(1),u("Comment posted!","ok"))}catch(i){u(i.message||"Could not post comment.","bad")}finally{a&&(a.disabled=!1)}})}function A(){document.addEventListener("click",t=>{var e;const n=t.target.closest(".bk-reply-trigger");if(n){const a=n.dataset.parent,s=document.querySelector(`.bk-reply-form[data-parent="${a}"]`);s&&(s.hidden=!1,(e=s.querySelector("input"))==null||e.focus());return}const o=t.target.closest(".bk-reply-cancel");if(o){const a=o.closest(".bk-reply-form");a&&(a.hidden=!0,a.reset())}}),document.addEventListener("submit",async t=>{const n=t.target.closest(".bk-reply-form");if(!n)return;t.preventDefault();const o=n.dataset.parent,e=n.querySelector("input"),a=((e==null?void 0:e.value)||"").trim();if(!a)return;const s=n.querySelector('button[type="submit"]');s&&(s.disabled=!0);try{const c=await L(a,o);if(c){const i=document.querySelector(`[data-replies-of="${o}"]`);i==null||i.insertAdjacentHTML("beforeend",T(c)),e.value="",n.hidden=!0,v(1)}}catch(c){u(c.message||"Could not reply.","bad")}finally{s&&(s.disabled=!1)}})}document.addEventListener("DOMContentLoaded",()=>{S(),E(),x(),A(),g(document)});
