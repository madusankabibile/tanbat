const APP = window.__APP__;
const $ = (s) => document.querySelector(s);
const toast = window.Tanbat.toast;
const api   = window.Tanbat.api;

let quill;

// Categories
async function loadCategories() {
  try {
    const cats = await api(APP.urls.api.categories);
    const sel = $('#aCategory');
    sel.innerHTML = '<option value="">Choose a category…</option>' +
      cats.map((c) => `<option value="${c.id}">${c.name}</option>`).join('');
  } catch (e) { console.warn(e); }
}

// Featured image preview
function bindFeatured() {
  $('#aFeatured').addEventListener('change', (e) => {
    const f = e.target.files[0]; if (!f) return;
    const r = new FileReader();
    r.onload = (ev) => {
      const img = $('#aFeaturedPreview');
      img.src = ev.target.result;
      img.classList.remove('hidden');
      $('#aFeaturedHint').classList.add('hidden');
    };
    r.readAsDataURL(f);
  });
}

// Short description word counter (HTML 5 maxlength won't word-count)
function bindShort() {
  const ta = $('#aShort'); const c = $('#aShortCount');
  const update = () => {
    const w = (ta.value.trim().match(/\S+/g) || []).length;
    c.textContent = w;
    c.style.color = w > 100 ? '#EF4444' : '#6B7280';
  };
  ta.addEventListener('input', update);
  update();
}

// Quill editor with image upload
function initQuill() {
  quill = new Quill('#aEditor', {
    theme: 'snow',
    placeholder: 'Tell your story…',
    modules: {
      toolbar: {
        container: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ color: [] }, { background: [] }],
          [{ list: 'ordered' }, { list: 'bullet' }],
          [{ align: [] }],
          ['blockquote', 'code-block'],
          ['link', 'image', 'video'],
          ['clean'],
        ],
        handlers: {
          image: insertInlineImage,
        },
      },
    },
  });
}

function insertInlineImage() {
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = async () => {
    const file = input.files[0];
    if (!file) return;
    const fd = new FormData();
    fd.append('file', file);
    try {
      toast('Uploading image…');
      const { url } = await api(APP.urls.api.inlineImage, { method: 'POST', body: fd });
      const range = quill.getSelection(true);
      quill.insertEmbed(range.index, 'image', url, 'user');
      quill.setSelection(range.index + 1);
    } catch (e) { toast(e.message, 'bad'); }
  };
  input.click();
}

// Submit
function bindSubmit() {
  $('#articleForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const title = $('#aTitle').value.trim();
    const short = $('#aShort').value.trim();
    const cat   = $('#aCategory').value;
    const tags  = $('#aTags').value.trim();
    const featured = $('#aFeatured').files[0];
    const html = quill.root.innerHTML;
    const wc = (short.match(/\S+/g) || []).length;

    if (!title) return toast('Add a title', 'bad');
    if (!featured) return toast('Pick a featured image', 'bad');
    if (!cat) return toast('Choose a category', 'bad');
    if (!short) return toast('Add a short description', 'bad');
    if (wc > 100) return toast('Short description must be 100 words or less', 'bad');
    if (quill.getLength() < 5) return toast('Write some content first', 'bad');

    const fd = new FormData();
    fd.append('title', title);
    fd.append('short_description', short);
    fd.append('category_id', cat);
    fd.append('tags', tags);
    fd.append('body', html);
    fd.append('featured_image', featured);

    const btn = $('#aPublish');
    btn.disabled = true; const old = btn.textContent; btn.textContent = 'Publishing…';
    try {
      const { url } = await api(APP.urls.api.articlePost, { method: 'POST', body: fd });
      toast('Article published!', 'ok');
      setTimeout(() => { location.href = url; }, 600);
    } catch (err) {
      btn.disabled = false; btn.textContent = old;
      toast(err.message, 'bad');
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  loadCategories();
  bindFeatured();
  bindShort();
  initQuill();
  bindSubmit();
});
