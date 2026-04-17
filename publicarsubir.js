(() => {
  const content = document.getElementById("contenido");
  const count = document.getElementById("psCount");
  const fileInput = document.getElementById("imagenes");
  const drop = document.getElementById("psDrop");
  const pick = document.getElementById("psPick");
  const thumbs = document.getElementById("psThumbs");
  const imgsCount = document.getElementById("psImgsCount");
  const catGrid = document.getElementById("psCatGrid");
  const catCount = document.getElementById("psCatCount");
  const tmdbSearch = document.getElementById("psTmdbSearch");
  const tmdbTypeFilter = document.getElementById("psTmdbType");
  const tmdbBtn = document.getElementById("psTmdbBtn");
  const tmdbResults = document.getElementById("psTmdbResults");
  const tmdbSelected = document.getElementById("psTmdbSelected");

  const tmdbIdField = document.getElementById("tmdbId");
  const tmdbTypeField = document.getElementById("tmdbType");
  const tmdbTitleField = document.getElementById("tmdbTitle");
  const tmdbPosterField = document.getElementById("tmdbPoster");
  const tmdbReleaseDateField = document.getElementById("tmdbReleaseDate");
  const tmdbOverviewField = document.getElementById("tmdbOverview");

  const MAX = 4;
  const MAX_CATS = 3;

  function updateCount() {
    if (!content || !count) return;
    const len = (content.value || "").length;
    count.textContent = `${len} / 500`;
  }

  function setImagesCount(n) {
    if (!imgsCount) return;
    imgsCount.textContent = `Imágenes: ${n} / ${MAX}`;
  }

  function clearThumbs() {
    if (!thumbs) return;
    thumbs.innerHTML = "";
  }

  function buildThumb(file, idx, onRemove) {
    const wrap = document.createElement("div");
    wrap.className = "ps-thumb";

    const img = document.createElement("img");
    img.alt = `Imagen ${idx + 1}`;
    img.src = URL.createObjectURL(file);
    img.onload = () => URL.revokeObjectURL(img.src);

    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "ps-rm";
    btn.textContent = "×";
    btn.addEventListener("click", () => onRemove(idx));

    wrap.appendChild(img);
    wrap.appendChild(btn);
    return wrap;
  }

  function syncFromFiles(files) {
    if (!fileInput) return;
    const list = Array.from(files || []).slice(0, MAX);
    const dt = new DataTransfer();
    list.forEach((f) => dt.items.add(f));
    fileInput.files = dt.files;
    renderThumbs();
  }

  function renderThumbs() {
    if (!fileInput || !thumbs) return;
    const files = Array.from(fileInput.files || []);
    clearThumbs();
    setImagesCount(files.length);

    files.slice(0, MAX).forEach((file, idx) => {
      thumbs.appendChild(
        buildThumb(file, idx, (removeIdx) => {
          const next = files.filter((_, i) => i !== removeIdx);
          syncFromFiles(next);
        })
      );
    });
  }

  if (content) {
    updateCount();
    content.addEventListener("input", updateCount);
  }

  if (pick && fileInput) {
    pick.addEventListener("click", () => fileInput.click());
  }

  if (fileInput) {
    fileInput.addEventListener("change", () => {
      const files = Array.from(fileInput.files || []);
      syncFromFiles(files);
    });
  }

  if (drop && fileInput) {
    const setDrag = (on) => drop.classList.toggle("dragover", on);

    ["dragenter", "dragover"].forEach((ev) => {
      drop.addEventListener(ev, (e) => {
        e.preventDefault();
        setDrag(true);
      });
    });

    ["dragleave", "drop"].forEach((ev) => {
      drop.addEventListener(ev, (e) => {
        e.preventDefault();
        setDrag(false);
      });
    });

    drop.addEventListener("drop", (e) => {
      const files = Array.from(e.dataTransfer?.files || []).filter((f) =>
        /^image\/(png|jpeg)$/.test(f.type)
      );
      if (!files.length) return;

      const merged = [
        ...Array.from(fileInput.files || []),
        ...files,
      ].slice(0, MAX);
      syncFromFiles(merged);
    });
  }

  setImagesCount(0);
  renderThumbs();

  // Categorías deja seleccionar solo 3
  function updateCatsUI() {
    if (!catGrid) return;
    const boxes = Array.from(catGrid.querySelectorAll('input[type="checkbox"]'));
    const selected = boxes.filter((b) => b.checked);
    const atMax = selected.length >= MAX_CATS;

    boxes.forEach((b) => {
      if (!b.checked) b.disabled = atMax;
    });

    if (catCount) catCount.textContent = `Categorías: ${selected.length} / ${MAX_CATS}`;
  }

  if (catGrid) {
    catGrid.addEventListener("change", updateCatsUI);
    updateCatsUI();
  }

  function yearFromDate(dateStr) {
    return String(dateStr || "").slice(0, 4);
  }

  function writeSelectedTmdb(item) {
    if (!tmdbIdField || !tmdbTypeField || !tmdbTitleField) return;

    if (!item) {
      tmdbIdField.value = "";
      tmdbTypeField.value = "";
      tmdbTitleField.value = "";
      if (tmdbPosterField) tmdbPosterField.value = "";
      if (tmdbReleaseDateField) tmdbReleaseDateField.value = "";
      if (tmdbOverviewField) tmdbOverviewField.value = "";
      return;
    }

    tmdbIdField.value = String(item.tmdb_id || "");
    tmdbTypeField.value = String(item.media_type || "");
    tmdbTitleField.value = String(item.title || "");
    if (tmdbPosterField) tmdbPosterField.value = String(item.poster_url || "");
    if (tmdbReleaseDateField) tmdbReleaseDateField.value = String(item.release_date || "");
    if (tmdbOverviewField) tmdbOverviewField.value = String(item.overview || "");
  }

  function readSelectedTmdb() {
    const id = (tmdbIdField?.value || "").trim();
    const type = (tmdbTypeField?.value || "").trim();
    const title = (tmdbTitleField?.value || "").trim();
    const poster = (tmdbPosterField?.value || "").trim();
    const releaseDate = (tmdbReleaseDateField?.value || "").trim();
    const overview = (tmdbOverviewField?.value || "").trim();

    if (!id || !type || !title) return null;

    return {
      tmdb_id: Number(id),
      media_type: type,
      title,
      poster_url: poster,
      release_date: releaseDate,
      overview,
    };
  }

  function escapeAttr(value) {
    return String(value || "").replace(/"/g, "&quot;");
  }

  function setResultsVisible(visible) {
    if (!tmdbResults) return;
    tmdbResults.style.display = visible ? "" : "none";
    tmdbResults.setAttribute("aria-hidden", visible ? "false" : "true");
  }

  function renderSelectedTmdb() {
    if (!tmdbSelected) return;
    const item = readSelectedTmdb();
    if (!item) {
      tmdbSelected.innerHTML = '<div class="ps-tmdb-empty">Sin titulo vinculado.</div>';
      setResultsVisible(true);
      return;
    }

    const labelType = item.media_type === "tv" ? "Serie" : "Pelicula";
    const year = yearFromDate(item.release_date);
    const posterHtml = item.poster_url
      ? `<img class="ps-tmdb-poster" src="${item.poster_url}" alt="Poster de ${item.title}">`
      : '<div class="ps-tmdb-noimg">Sin poster</div>';

    tmdbSelected.innerHTML = `
      <div class="ps-tmdb-picked">
        ${posterHtml}
        <div class="ps-tmdb-picked-meta">
          <strong>${item.title}</strong>
          <span>${labelType}${year ? ` (${year})` : ""}</span>
        </div>
        <button type="button" class="ps-tmdb-clear" aria-label="Quitar seleccion">×</button>
      </div>
    `;

    const clearBtn = tmdbSelected.querySelector(".ps-tmdb-clear");
    if (clearBtn) {
      clearBtn.addEventListener("click", () => {
        writeSelectedTmdb(null);
        renderSelectedTmdb();
        setResultsVisible(true);
        if (tmdbResults) tmdbResults.innerHTML = '<div class="ps-tmdb-empty">Escribe 2 letras para buscar una pelicula o serie.</div>';
      });
    }
  }

  async function searchTmdb() {
    if (!tmdbSearch || !tmdbTypeFilter || !tmdbResults) return;
    const q = (tmdbSearch.value || "").trim();
    setResultsVisible(true);

    if (q.length < 2) {
      tmdbResults.innerHTML = '<div class="ps-tmdb-empty">Escribe al menos 2 letras para buscar.</div>';
      return;
    }

    if (tmdbBtn) tmdbBtn.disabled = true;
    tmdbResults.innerHTML = '<div class="ps-tmdb-empty">Buscando en TMDB...</div>';

    try {
      const params = new URLSearchParams({ q, type: tmdbTypeFilter.value || "multi" });
      const res = await fetch(`tmdb_search.php?${params.toString()}`, { credentials: "same-origin" });
      const data = await res.json().catch(() => null);
      if (!res.ok || !data || !data.ok) throw new Error((data && data.error) || "No se pudo buscar");
      renderTmdbResults(data.results || []);
    } catch (error) {
      tmdbResults.innerHTML = `<div class="ps-tmdb-empty">${error.message || "Error al buscar en TMDB."}</div>`;
    } finally {
      if (tmdbBtn) tmdbBtn.disabled = false;
    }
  }

  function renderTmdbResults(items) {
    if (!tmdbResults) return;
    if (!Array.isArray(items) || !items.length) {
      tmdbResults.innerHTML = '<div class="ps-tmdb-empty">No se encontraron resultados.</div>';
      return;
    }

    tmdbResults.innerHTML = items
      .slice(0, 8)
      .map((item) => {
        const labelType = item.media_type === "tv" ? "Serie" : "Pelicula";
        const year = yearFromDate(item.release_date);
        const poster = item.poster_url
          ? `<img class="ps-tmdb-poster" src="${item.poster_url}" alt="Poster">`
          : '<div class="ps-tmdb-noimg">Sin poster</div>';
        const fullOverview = item.overview || "";
        const safeOverview = String(fullOverview).slice(0, 120);

        return `
          <button type="button" class="ps-tmdb-item" data-id="${escapeAttr(item.tmdb_id)}" data-type="${escapeAttr(item.media_type || "movie")}" data-title="${escapeAttr(item.title)}" data-poster="${escapeAttr(item.poster_url)}" data-release="${escapeAttr(item.release_date)}" data-overview="${escapeAttr(fullOverview)}">
            ${poster}
            <span class="ps-tmdb-main">
              <strong>${item.title}</strong>
              <small>${labelType}${year ? ` (${year})` : ""}</small>
              ${safeOverview ? `<small>${safeOverview}</small>` : ""}
            </span>
          </button>
        `;
      })
      .join("");
  }

  // Delegación de eventos para clicks en películas
  document.addEventListener("click", (event) => {
    const item = event.target.closest(".ps-tmdb-item");
    if (!item || !tmdbResults || !tmdbResults.contains(item)) return;

    const data = {
      tmdb_id: Number(item.dataset.id || 0),
      media_type: item.dataset.type || "movie",
      title: item.dataset.title || "",
      poster_url: item.dataset.poster || "",
      release_date: item.dataset.release || "",
      overview: item.dataset.overview || "",
    };

    if (!data.tmdb_id || !data.title) {
      console.log("Datos inválidos:", data);
      return;
    }

    console.log("Película seleccionada:", data);
    writeSelectedTmdb(data);
    renderSelectedTmdb();
    if (tmdbResults) tmdbResults.innerHTML = "";
    setResultsVisible(false);
  });

  if (tmdbBtn) {
    tmdbBtn.addEventListener("click", () => {
      setResultsVisible(true);
      searchTmdb();
    });
  }

  if (tmdbSearch) {
    let tmdbTimer = null;

    tmdbSearch.addEventListener("input", () => {
      setResultsVisible(true);
      if (tmdbTimer) clearTimeout(tmdbTimer);
      tmdbTimer = setTimeout(searchTmdb, 250);
    });

    tmdbSearch.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        searchTmdb();
      }
    });
  }

  if (tmdbResults && !(tmdbSearch?.value || '').trim()) {
    tmdbResults.innerHTML = '<div class="ps-tmdb-empty">Escribe 2 letras para buscar una pelicula o serie.</div>';
  }

  renderSelectedTmdb();
  setResultsVisible(!readSelectedTmdb());
})();
