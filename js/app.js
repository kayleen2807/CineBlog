// ==================== CROPPER SETUP ====================
const fileInput = document.getElementById("foto");
const applyBtn = document.getElementById("apply-btn");
const cropContainer = document.getElementById("crop-container");
const cropSource = document.getElementById("crop-source");
const previewContainer = document.getElementById("preview-container");
const previewImg = document.getElementById("preview-img");

let cropper = null;
let croppedBlob = null;

// Funcion que limpia el estado del cropper y oculta las secciones relacionadas
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

// Configuración del input de archivo para manejar la selección de imagen y el proceso de recorte
if (fileInput) {
    // Al cambiar el archivo seleccionado, se carga la imagen y se inicializa Cropper.js
    fileInput.addEventListener("change", (event) => {
        const file = event.target.files?.[0];
        if (!file) {
            resetCropper();
            return;
        }

        // Crear una URL temporal para la imagen seleccionada y cargarla en el elemento de origen del cropper
        const url = URL.createObjectURL(file);
        cropSource.src = url;
        cropContainer.classList.remove("hidden");

        // Manejo de errores al cargar la imagen
        cropSource.onerror = () => {
            console.error("Error al cargar la imagen seleccionada.");
        };

        // Cuando la imagen se carga correctamente, se inicializa Cropper.js con las opciones deseadas
        cropSource.onload = () => {
            if (cropper) cropper.destroy();

            // Inicializar Cropper.js con opciones específicas para recortar la imagen en un área cuadrada
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

        // Mostrar el botón de aplicar y ocultar la vista previa hasta que se recorte una nueva imagen
        applyBtn.classList.remove("hidden");
        previewContainer.classList.add("hidden");
    });

    // Al hacer clic en el botón de aplicar, se obtiene el área recortada como un blob y se envía al servidor para su procesamiento
    if (applyBtn) applyBtn.addEventListener("click", async () => {
        // Validar que el cropper esté inicializado antes de intentar obtener el área recortada
        if (!cropper) {
            alert("Por favor, selecciona y ajusta una imagen primero.");
            return;
        }
        // Deshabilitar el botón de aplicar para evitar múltiples envíos y mostrar un mensaje de carga
        applyBtn.disabled = true;
        applyBtn.textContent = "Subiendo...";

        // Obtener el área recortada como un canvas con las dimensiones y calidad especificadas
        const canvas = cropper.getCroppedCanvas({
            width: 400,
            height: 400,
            imageSmoothingQuality: "high",
            fillColor: "#ffffff",
        });

        // Convertir el canvas a un blob de imagen JPEG y manejar el resultado para subirlo al servidor
        canvas.toBlob(async (blob) => {
            if (!blob) {
                alert("Error al procesar la imagen.");
                applyBtn.disabled = false;
                applyBtn.textContent = "Aplicar y subir";
                return;
            }

            // Crear un FormData para enviar el blob de la imagen recortada al servidor a través de una solicitud POST
            const formData = new FormData();
            formData.append("foto", blob, "perfil_cortado.jpg");

            // Enviar la imagen recortada al servidor utilizando fetch y manejar la respuesta para actualizar la interfaz de usuario
            try {
                // Realizar la solicitud POST para subir la imagen recortada al servidor
                const response = await fetch("subirFoto.php", {
                    method: "POST",
                    body: formData,
                });
                // Si la respuesta es exitosa, recargar la página para mostrar la nueva imagen de perfil; de lo contrario, mostrar un mensaje de error
                if (response.ok) {
                    window.location.reload();
                } else {
                    const text = await response.text();
                    console.error("Error al subir:", text);
                    alert("Error al subir la imagen. Revisa la consola.");
                    applyBtn.disabled = false;
                    applyBtn.textContent = "Aplicar y subir";
                }
            // Manejar errores de red o problemas inesperados durante la solicitud de subida y mostrar un mensaje de error al usuario
            } catch (error) {
                console.error("Error de red:", error);
                alert("Error de conexión. Intenta de nuevo.");
                applyBtn.disabled = false;
                applyBtn.textContent = "Aplicar y subir";
            }
        }, "image/jpeg", 0.92);
    });
}

//Funcion para configurar elementos que deben tener una clase activa exclusiva al hacer clic, como enlaces de navegación o pestañas
function setupExclusiveActive(selector, { preventDefault = false, activeClass = "active" } = {}) {
    // Seleccionar todos los elementos que coinciden con el selector proporcionado y agregar un controlador de eventos para manejar los clics
    const items = Array.from(document.querySelectorAll(selector));
    if (!items.length) return;

    // Al hacer clic en un elemento, se previene el comportamiento predeterminado si se especifica, se elimina la clase activa de todos los elementos y se agrega la clase activa al elemento clicado
    items.forEach((item) => {
        item.addEventListener("click", (event) => {
            if (preventDefault) event.preventDefault();
            items.forEach((other) => other.classList.remove(activeClass));
            item.classList.add(activeClass);
        });
    });
}

// Función para configurar elementos que deben mostrar una clase de "flash" temporal al hacer clic, como pestañas o botones de métricas
function setupFlash(selector, { durationMs = 1800, preventDefault = false, flashClass = "flash" } = {}) {
    // Seleccionar todos los elementos que coinciden con el selector proporcionado y agregar un controlador de eventos para manejar los clics, mostrando una clase de "flash" temporal para resaltar la interacción del usuario
    const items = Array.from(document.querySelectorAll(selector));
    if (!items.length) return;
    // Utilizar un WeakMap para almacenar los temporizadores de cada elemento y asegurarse de que se limpien correctamente al eliminar la clase de "flash" después del tiempo especificado
    const timeouts = new WeakMap();

    // Al hacer clic en un elemento, se previene el comportamiento predeterminado si se especifica
    items.forEach((item) => {
        // Si el elemento ya tiene un temporizador activo, se limpia para evitar que la clase de "flash" se elimine prematuramente
        item.addEventListener("click", (event) => {
            // Prevenir el comportamiento predeterminado del clic si se ha configurado para hacerlo
            if (preventDefault) event.preventDefault();

            // Si el elemento ya tiene un temporizador activo, se limpia para evitar que la clase de "flash" se elimine prematuramente
            const existing = timeouts.get(item);
            if (existing) clearTimeout(existing);

            // Agregar la clase de "flash" al elemento clicado para resaltar la interacción del usuario y configurar un temporizador para eliminar la clase después de la duración especificada
            item.classList.add(flashClass);
            const timeout = setTimeout(() => {
                item.classList.remove(flashClass);
                timeouts.delete(item);
            }, durationMs);
            timeouts.set(item, timeout);
        });
    });
}

//Funcion para generar un URI de datos SVG que sirve como imagen de marcador de posición para elementos <img> que no se cargan correctamente, mostrando un fondo degradado y un texto alternativo centrado
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

// Función para configurar un mecanismo de fallback para imágenes que no se cargan correctamente
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

// Función para configurar botones de "Mostrar más" que expanden o colapsan el texto de reseñas, cambiando el texto del botón entre "Mostrar más" y "Mostrar menos" según el estado actual
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

// Configuración de interacciones globales para el sitio, incluyendo navegación activa, pestañas con efecto de "flash", estadísticas resaltadas, botones de métricas, etiquetas activas, manejo de imágenes con fallback y botones de "Mostrar más" para reseñas
setupExclusiveActive(".nav-link", { preventDefault: true });
setupFlash(".tab", { preventDefault: true, durationMs: 1600 });
setupFlash(".stat[data-stat]");
setupFlash(".metric-btn", { durationMs: 1600 });
setupExclusiveActive(".tags .tag");
function normalizeFilterValue(value) {
    const raw = String(value || "");
    const normalized = typeof raw.normalize === "function"
        ? raw.normalize("NFD").replace(/[\u0300-\u036f]/g, "")
        : raw;
    return normalized
        .toLowerCase()
        .replace(/\s+/g, " ")
        .trim();
}

function applyFeedFilters() {
    const feed = document.querySelector(".feed-inner");
    const cards = Array.from(document.querySelectorAll(".post-card"));
    if (!feed || !cards.length) return;

    const activeType = feed.dataset.activeType || feed.dataset.initialType || "";
    const activeCat = normalizeFilterValue(feed.dataset.activeCat || feed.dataset.initialCat || "");

    cards.forEach((card) => {
        const cardType = card.dataset.type || "movie";
        const typeMatches = activeType === ""
            ? true
            : activeType === "series"
                ? cardType === "series"
                : cardType !== "series";
        const cats = String(card.dataset.cats || "").split("|").map(normalizeFilterValue).filter(Boolean);
        const catMatches = activeCat === "" || cats.includes(activeCat);
        const shouldShow = typeMatches && catMatches;
        card.hidden = !shouldShow;
        card.style.display = shouldShow ? "" : "none";
    });

    const visibleCount = cards.filter((card) => !card.hidden).length;
    let empty = feed.querySelector(".feed-filter-empty");
    if (!empty) {
        empty = document.createElement("div");
        empty.className = "feed-empty feed-filter-empty";
        empty.textContent = "No hay publicaciones para este filtro.";
        feed.appendChild(empty);
    }
    empty.hidden = visibleCount > 0;
}

window.selType = function selType(button, type) {
    const feed = document.querySelector(".feed-inner");
    if (!feed) return;
    feed.dataset.activeType = type === "series" ? "series" : "movies";
    document.querySelectorAll(".type-btn").forEach((item) => item.classList.remove("active"));
    if (button) button.classList.add("active");
    applyFeedFilters();
};

window.selPill = function selPill(pill) {
    const feed = document.querySelector(".feed-inner");
    if (!feed || !pill) return;
    const wasOn = pill.classList.contains("on");
    document.querySelectorAll(".pill").forEach((item) => item.classList.remove("on"));
    if (wasOn) {
        feed.dataset.activeCat = "";
    } else {
        pill.classList.add("on");
        const raw = pill.dataset.cat || pill.textContent;
        feed.dataset.activeCat = normalizeFilterValue(raw);
    }
    applyFeedFilters();
};

function setupFeedFilters() {
    const feed = document.querySelector(".feed-inner");
    if (!feed) return;
    feed.dataset.activeType = feed.dataset.initialType || "";
    feed.dataset.activeCat = feed.dataset.initialCat || "";

    document.addEventListener("click", (event) => {
        const pill = event.target.closest(".pill");
        if (pill) {
            window.selPill(pill);
            return;
        }
        const cat = event.target.closest(".post-cat[data-cat]");
        if (!cat) return;
        const value = normalizeFilterValue(cat.dataset.cat || cat.textContent);
        const pills = Array.from(document.querySelectorAll(".pill"));
        const matchedPill = pills.find((item) => {
            const pillValue = item.dataset.cat || item.textContent;
            return normalizeFilterValue(pillValue) === value;
        });
        if (matchedPill) {
            window.selPill(matchedPill);
            return;
        }
        pills.forEach((item) => item.classList.remove("on"));
        feed.dataset.activeCat = value;
        applyFeedFilters();
    });

    applyFeedFilters();
}

setupImageFallback();
setupShowMore();
setupFeedFilters();

function setupUserMenu() {
    const trigger = document.querySelector(".user-menu-trigger");
    const menu = document.querySelector(".user-menu");
    if (!trigger || !menu) return;

    const closeMenu = () => {
        menu.classList.remove("open");
        trigger.setAttribute("aria-expanded", "false");
    };

    trigger.addEventListener("click", (event) => {
        event.stopPropagation();
        const isOpen = menu.classList.toggle("open");
        trigger.setAttribute("aria-expanded", isOpen ? "true" : "false");
    });

    document.addEventListener("click", (event) => {
        if (menu.contains(event.target) || trigger.contains(event.target)) return;
        closeMenu();
    });

    menu.addEventListener("click", (event) => {
        const item = event.target.closest(".user-menu-item");
        if (item) closeMenu();
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") closeMenu();
    });
}

function setupProfileShare() {
    const button = document.querySelector(".profile-share");
    if (!button) return;

    button.addEventListener("click", async () => {
        const url = window.location.href;
        try {
            if (navigator.share) {
                await navigator.share({ title: "CineBlog", url });
                return;
            }
            await navigator.clipboard.writeText(url);
            showInlineToast("Enlace copiado al portapapeles.");
        } catch {
            window.prompt("Copia este enlace:", url);
        }
    });
}

function showInlineToast(message) {
    const toast = document.createElement("div");
    toast.className = "ui-toast";
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3200);
}

setupUserMenu();
setupProfileShare();

function setupBackButton() {
    const button = document.querySelector("[data-back]");
    if (!button) return;

    button.addEventListener("click", (event) => {
        event.preventDefault();
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = "index.php";
        }
    });
}

setupBackButton();

// Funcion para configurar la funcionalidad de "Me gusta" en las publicaciones/posts
function setupLikes() {
    document.addEventListener("click", async (event) => {
        // Verificar si el clic se realizó en un botón de "Me gusta" que tenga un atributo de identificación de publicación
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
            // Enviar una solicitud POST al servidor para alternar el estado de "Me gusta"
            const body = new URLSearchParams();
            body.set("post_id", postId);
            // La respuesta del servidor se espera que sea un JSON con un campo "liked" que indique el nuevo estado de "Me gusta"
            const res = await fetch("toggle_like.php", {
                method: "POST",
                credentials: "same-origin",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: body.toString(),
            });
            // Si la respuesta no es exitosa o no contiene los datos esperados, se lanza un error para revertir el cambio en la interfaz de usuario
            const data = await res.json().catch(() => null);
            if (!res.ok || !data || !data.ok) throw new Error("like failed");
            // Actualizar el estado del botón de "Me gusta" según la respuesta del servidor
            const liked = !!data.liked;
            btn.classList.toggle("liked", liked);
            btn.setAttribute("aria-pressed", liked ? "true" : "false");
        } catch {
            // Si falla, deja el UI como está (para que el usuario vea el cambio).
        }
    });
}

//Función para configurar la funcionalidad de comentarios en las publicaciones/posts, incluyendo la capacidad de mostrar u ocultar la sección de comentarios y enviar nuevos comentarios al servidor
function setupComments() {
    // Función auxiliar para escapar correctamente los valores de los selectores de atributos, evitando problemas con caracteres especiales como comillas o barras invertidas
    const escapeAttrSelectorValue = (value) => String(value ?? "").replace(/\\/g, "\\\\").replace(/"/g, '\\"');

    // Manejar clics en botones de comentarios para mostrar u ocultar la sección de comentarios correspondiente a una publicación específica
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

    // Manejar el envío de formularios de comentarios para agregar un nuevo comentario a una publicación específica, enviando los datos al servidor y actualizando la interfaz de usuario con el nuevo comentario si la solicitud es exitosa
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

        // Enviar la solicitud POST al servidor para agregar el nuevo comentario, esperando una respuesta JSON que indique si la operación fue exitosa
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
`.trim(); // Rellenar los campos del nuevo comentario con los datos recibidos del servidor o con valores predeterminados si no se proporcionan, y agregar el nuevo comentario a la lista de comentarios en la interfaz de usuario
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

// Función para configurar una búsqueda global en TMDB que permite a los usuarios buscar películas o series directamente desde un campo de búsqueda
function setupGlobalTmdbSearch() {
    // Seleccionar el campo de entrada para la búsqueda global y el panel donde se mostrarán los resultados, y configurar los eventos necesarios para manejar la interacción del usuario con la búsqueda
    const input = document.getElementById("tmdbGlobalSearch");
    const panel = document.getElementById("tmdbGlobalResults");
    if (!input || !panel) return;

    let timer = null;

    const closePanel = () => {
        panel.classList.remove("open");
        panel.innerHTML = "";
    };

    // Función para renderizar los resultados de la búsqueda en el panel, mostrando un mensaje si no hay resultados
    const render = (items) => {
        // Si no se reciben resultados válidos, mostrar un mensaje de "Sin resultados" en el panel
        if (!Array.isArray(items) || !items.length) {
            panel.innerHTML = '<div class="search-empty">Sin resultados.</div>';
            panel.classList.add("open");
            return;
        }

        // Renderizar los resultados de la búsqueda en el panel, mostrando hasta 8 resultados con su título, tipo (película o serie), año de lanzamiento y poster si está disponible, y agregando un enlace a la página de detalles correspondiente para cada resultado
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

    // Función para ejecutar la búsqueda en TMDB, enviando una solicitud al servidor con el término de búsqueda y actualizando el panel con los resultados o mostrando un mensaje de error si la consulta falla
    const runSearch = async () => {
        const q = (input.value || "").trim();
        if (q.length < 2) {
            closePanel();
            return;
        }

        panel.innerHTML = '<div class="search-empty">Buscando...</div>';
        panel.classList.add("open");

        // Enviar una solicitud al servidor para buscar en TMDB utilizando el término de búsqueda proporcionado
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

    // Configurar un temporizador para ejecutar la búsqueda después de que el usuario deje de escribir durante 260 ms
    input.addEventListener("input", () => {
        if (timer) clearTimeout(timer);
        timer = setTimeout(runSearch, 260);
    });

    // Al enfocar el campo de búsqueda, si hay un término de búsqueda válido y el panel no está vacío, se muestra el panel con los resultados de la búsqueda
    input.addEventListener("focus", () => {
        if ((input.value || "").trim().length >= 2 && panel.innerHTML.trim() !== "") {
            panel.classList.add("open");
        }
    });

    // Al hacer clic fuera del campo de búsqueda o del panel de resultados, se cierra el panel para ocultar los resultados de la búsqueda
    document.addEventListener("click", (event) => {
        if (event.target === input || panel.contains(event.target)) return;
        closePanel();
    });
}

// Configuración de la búsqueda global en TMDB, la funcionalidad de "Me gusta" en las publicaciones/posts y la funcionalidad de comentarios en las publicaciones/posts
setupGlobalTmdbSearch();
setupLikes();
setupComments();

// ... aquí van tus otras funciones y scripts globales

// 🔹 Script para menú de opciones en publicaciones
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".options-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      const menu = btn.nextElementSibling;
      menu.style.display = menu.style.display === "block" ? "none" : "block";
    });
  });

  window.addEventListener("click", e => {
    document.querySelectorAll(".options-menu").forEach(menu => {
      if (!menu.contains(e.target) && !menu.previousElementSibling.contains(e.target)) {
        menu.style.display = "none";
      }
    });
  });
});


