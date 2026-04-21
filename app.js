// ==================== CROPPER SETUP ====================
const fileInput = document.getElementById("foto");
const applyBtn = document.getElementById("apply-btn");
const cropContainer = document.getElementById("crop-container");
const cropSource = document.getElementById("crop-source");
const previewContainer = document.getElementById("preview-container");
const previewImg = document.getElementById("preview-img");

let cropper = null;
let croppedBlob = null;

function resetCropper() {
    if (cropper) {
        cropper.destroy();
        cropper = null;
    }
    cropContainer.classList.add("hidden");
    previewContainer.classList.add("hidden");
    applyBtn.classList.add("hidden");
    croppedBlob = null;
    cropSource.src = "";
    previewImg.src = "";
}

if (fileInput) {
    fileInput.addEventListener("change", (event) => {
        const file = event.target.files?.[0];
        if (!file) {
            resetCropper();
            return;
        }

        const url = URL.createObjectURL(file);
        cropSource.src = url;
        cropContainer.classList.remove("hidden");

        cropSource.onerror = () => {
            console.error("Image failed to load");
        };

        cropSource.onload = () => {
            if (cropper) cropper.destroy();

            cropper = new Cropper(cropSource, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 0.8,
                background: false,
                movable: true,
                zoomable: true,
                scalable: false,
                rotatable: false,
                responsive: true,
            });
        };

        applyBtn.classList.remove("hidden");
        previewContainer.classList.add("hidden");
    });

    if (applyBtn) applyBtn.addEventListener("click", async () => {
        if (!cropper) {
            alert("Por favor, selecciona y ajusta una imagen primero.");
            return;
        }

        applyBtn.disabled = true;
        applyBtn.textContent = "Subiendo...";

        const canvas = cropper.getCroppedCanvas({
            width: 400,
            height: 400,
            imageSmoothingQuality: "high",
            fillColor: "#ffffff",
        });

        canvas.toBlob(async (blob) => {
            if (!blob) {
                alert("Error al procesar la imagen.");
                applyBtn.disabled = false;
                applyBtn.textContent = "Aplicar y subir";
                return;
            }

            const formData = new FormData();
            formData.append("foto", blob, "perfil_cortado.jpg");

            try {
                const response = await fetch("subirFoto.php", {
                    method: "POST",
                    body: formData,
                });

                if (response.ok) {
                    window.location.reload();
                } else {
                    const text = await response.text();
                    console.error("Error al subir:", text);
                    alert("Error al subir la imagen. Revisa la consola.");
                    applyBtn.disabled = false;
                    applyBtn.textContent = "Aplicar y subir";
                }
            } catch (error) {
                console.error("Error de red:", error);
                alert("Error de conexión. Intenta de nuevo.");
                applyBtn.disabled = false;
                applyBtn.textContent = "Aplicar y subir";
            }
        }, "image/jpeg", 0.92);
    });
}


function setupExclusiveActive(selector, { preventDefault = false, activeClass = "active" } = {}) {
    const items = Array.from(document.querySelectorAll(selector));
    if (!items.length) return;

    items.forEach((item) => {
        item.addEventListener("click", (event) => {
            if (preventDefault) event.preventDefault();
            items.forEach((other) => other.classList.remove(activeClass));
            item.classList.add(activeClass);
        });
    });
}

function setupFlash(selector, { durationMs = 1800, preventDefault = false, flashClass = "flash" } = {}) {
    const items = Array.from(document.querySelectorAll(selector));
    if (!items.length) return;

    const timeouts = new WeakMap();

    items.forEach((item) => {
        item.addEventListener("click", (event) => {
            if (preventDefault) event.preventDefault();

            const existing = timeouts.get(item);
            if (existing) clearTimeout(existing);

            item.classList.add(flashClass);
            const timeout = setTimeout(() => {
                item.classList.remove(flashClass);
                timeouts.delete(item);
            }, durationMs);
            timeouts.set(item, timeout);
        });
    });
}

function svgPlaceholderDataUri(label, bg = "#1b2433", fg = "#edf1f7") {
    const safe = String(label || "Imagen").slice(0, 18);
    const svg = `
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="600" viewBox="0 0 800 600">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="${bg}"/>
      <stop offset="1" stop-color="#0d1522"/>
    </linearGradient>
  </defs>
  <rect width="800" height="600" fill="url(#g)"/>
  <text x="50%" y="52%" fill="${fg}" font-family="Segoe UI, Tahoma, sans-serif" font-size="64" text-anchor="middle">
    ${safe}
  </text>
</svg>`;
    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg.trim())}`;
}

function setupImageFallback() {
    const images = Array.from(document.querySelectorAll("img"));
    if (!images.length) return;

    images.forEach((img) => {
        img.addEventListener("error", () => {
            if (img.dataset.fallbackApplied) return;
            img.dataset.fallbackApplied = "1";
            img.src = svgPlaceholderDataUri(img.alt || "Imagen");
            img.classList.add("img-fallback");
        });
    });
}

function setupShowMore() {
    const buttons = Array.from(document.querySelectorAll(".toggle-review"));
    if (!buttons.length) return;

    buttons.forEach((button) => {
        const card = button.closest(".card");
        const copy = card ? card.querySelector(".review-copy") : null;
        if (!copy) return;

        copy.classList.add("collapsed");

        const moreText = button.textContent.trim() || "Show More";
        const isEnglish = moreText.toLowerCase().includes("show");
        const lessText = isEnglish ? "Show Less" : "Mostrar menos";

        button.addEventListener("click", () => {
            const collapsed = copy.classList.contains("collapsed");
            copy.classList.toggle("collapsed", !collapsed);
            button.textContent = collapsed ? lessText : moreText;
        });
    });
}

setupExclusiveActive(".nav-link", { preventDefault: true });
setupFlash(".tab", { preventDefault: true, durationMs: 1600 });
setupFlash(".stat[data-stat]");
setupFlash(".metric-btn", { durationMs: 1600 });
setupExclusiveActive(".tags .tag");
setupImageFallback();
setupShowMore();

function setupLikes() {
    document.addEventListener("click", async (event) => {
        const btn = event.target.closest(".like-btn[data-post-id]");
        if (!btn) return;

        const postId = btn.dataset.postId;
        if (!postId) return;

        // Optimistic UI: cambia al instante
        const before = btn.classList.contains("liked");
        const next = !before;
        btn.classList.toggle("liked", next);
        btn.setAttribute("aria-pressed", next ? "true" : "false");

        try {
            const body = new URLSearchParams();
            body.set("post_id", postId);

            const res = await fetch("toggle_like.php", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: body.toString(),
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.ok) throw new Error("like failed");

            const liked = !!data.liked;
            btn.classList.toggle("liked", liked);
            btn.setAttribute("aria-pressed", liked ? "true" : "false");
        } catch {
            // Si falla, deja el UI como está (para que el usuario vea el cambio).
        }
    });
}

function setupComments() {
    const escapeAttrSelectorValue = (value) => String(value ?? "").replace(/\\/g, "\\\\").replace(/"/g, '\\"');

    document.addEventListener("click", (event) => {
        const btn = event.target.closest(".comment-btn[data-post-id]");
        if (!btn) return;

        const postId = btn.dataset.postId;
        if (!postId) return;

        const sec = document.querySelector(`.comments[data-post-id="${escapeAttrSelectorValue(postId)}"]`);
        if (!sec) return;

        const isHidden = sec.hasAttribute("hidden");
        if (isHidden) sec.removeAttribute("hidden");
        else sec.setAttribute("hidden", "");

        if (isHidden) {
            const input = sec.querySelector(".comment-input");
            if (input) input.focus();
        }
    });

    document.addEventListener("submit", async (event) => {
        const form = event.target.closest(".comment-form[data-post-id]");
        if (!form) return;
        event.preventDefault();

        const postId = form.dataset.postId;
        const input = form.querySelector(".comment-input");
        const commentsSection = form.closest(".comments");
        const list = commentsSection ? commentsSection.querySelector(".comment-list") : null;
        if (!postId || !input || !list) return;

        const text = (input.value || "").trim();
        if (!text) return;

        const body = new URLSearchParams();
        body.set("post_id", postId);
        body.set("contenido", text);

        const sendBtn = form.querySelector(".comment-send");
        if (sendBtn) sendBtn.disabled = true;

        try {
            const res = await fetch("add_comment.php", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: body.toString(),
            });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.ok) {
                const msg = (data && data.error) ? data.error : "No se pudo comentar.";
                alert(msg);
                return;
            }

            const c = data.comment || { autor: "Tú", fecha: "", contenido: text };
            const item = document.createElement("div");
            item.className = "comment-item";
            item.innerHTML = `
<div class="comment-head">
  <span class="comment-author"></span>
  <span class="comment-date"></span>
</div>
<div class="comment-body"></div>
`.trim();
            item.querySelector(".comment-author").textContent = c.autor || "Tú";
            item.querySelector(".comment-date").textContent = (c.fecha || "").slice(0, 16);
            item.querySelector(".comment-body").textContent = c.contenido || text;
            list.appendChild(item);

            input.value = "";
        } finally {
            if (sendBtn) sendBtn.disabled = false;
        }
    });
}

function setupGlobalTmdbSearch() {
    const input = document.getElementById("tmdbGlobalSearch");
    const panel = document.getElementById("tmdbGlobalResults");
    if (!input || !panel) return;

    let timer = null;

    const closePanel = () => {
        panel.classList.remove("open");
        panel.innerHTML = "";
    };

    const render = (items) => {
        if (!Array.isArray(items) || !items.length) {
            panel.innerHTML = '<div class="search-empty">Sin resultados.</div>';
            panel.classList.add("open");
            return;
        }

        panel.innerHTML = items.slice(0, 8).map((item) => {
            const type = item.media_type === "tv" ? "Serie" : "Pelicula";
            const year = String(item.release_date || "").slice(0, 4);
            const poster = item.poster_url
                ? `<img src="${item.poster_url}" alt="Poster de ${item.title}">`
                : '<div class="search-noimg">Sin poster</div>';
                        const pathType = item.media_type === "tv" ? "tv" : "movie";

            return `
<a class="search-item" href="pelicula.php?tmdb_id=${item.tmdb_id}&type=${pathType}">
  ${poster}
  <span class="search-meta">
    <strong>${item.title}</strong>
    <small>${type}${year ? ` (${year})` : ""}</small>
  </span>
</a>
`;
        }).join("");

        panel.classList.add("open");
    };

    const runSearch = async () => {
        const q = (input.value || "").trim();
        if (q.length < 2) {
            closePanel();
            return;
        }

        panel.innerHTML = '<div class="search-empty">Buscando...</div>';
        panel.classList.add("open");

        try {
            const params = new URLSearchParams({ q, type: "multi" });
            const res = await fetch(`tmdb_search.php?${params.toString()}`, { credentials: "same-origin" });
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.ok) throw new Error("Busqueda fallida");
            render(data.results || []);
        } catch {
            panel.innerHTML = '<div class="search-empty">No se pudo consultar TMDB.</div>';
            panel.classList.add("open");
        }
    };

    input.addEventListener("input", () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(runSearch, 260);
    });

    input.addEventListener("focus", () => {
        if ((input.value || "").trim().length >= 2 && panel.innerHTML.trim() !== "") {
            panel.classList.add("open");
        }
    });

    document.addEventListener("click", (event) => {
        if (event.target === input || panel.contains(event.target)) return;
        closePanel();
    });
}

setupGlobalTmdbSearch();
setupLikes();
setupComments();
