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
})();
