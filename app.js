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
    document.addEventListener("click", (event) => {
        const btn = event.target.closest(".comment-btn[data-post-id]");
        if (!btn) return;

        const postId = btn.dataset.postId;
        if (!postId) return;

        const safeId = window.CSS && typeof CSS.escape === "function" ? CSS.escape(postId) : postId;
        const sec = document.querySelector(`.comments[data-post-id="${safeId}"]`);
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

setupLikes();
setupComments();
