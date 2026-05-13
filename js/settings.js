document.addEventListener("DOMContentLoaded", () => {
    const body = document.body;
    const navLinks = [...document.querySelectorAll(".settings-nav-link")];
    const sections = navLinks
        .map((link) => document.querySelector(link.getAttribute("href")))
        .filter(Boolean);
    const revealItems = [...document.querySelectorAll(".reveal")];
    const themeSelect = document.getElementById("themeSelect");
    const profileForm = document.getElementById("profileForm");
    const settingsForm = document.getElementById("settingsForm");
    const dirtyMessage = document.getElementById("dirtyMessage");
    const saveStatus = document.getElementById("saveStatus");
    const deletePhotoButton = document.getElementById("deletePhotoButton");
    const deletePhotoForm = document.getElementById("deletePhotoForm");

    const storedTheme = localStorage.getItem("theme") || "dark";
    if (storedTheme === "light") {
        body.classList.add("light-settings");
    }
    if (themeSelect) {
        themeSelect.value = storedTheme === "light" ? "light" : "dark";
        themeSelect.addEventListener("change", () => {
            const isLight = themeSelect.value === "light";
            body.classList.toggle("light-settings", isLight);
            localStorage.setItem("theme", isLight ? "light" : "dark");
            showToast(isLight ? "Tema claro aplicado." : "Tema oscuro aplicado.");
            markDirty();
        });
    }

    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add("is-visible");
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.16 });

    revealItems.forEach((item) => revealObserver.observe(item));

    const sectionObserver = new IntersectionObserver((entries) => {
        const visible = entries
            .filter((entry) => entry.isIntersecting)
            .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];

        if (!visible) return;
        navLinks.forEach((link) => {
            link.classList.toggle("active", link.getAttribute("href") === `#${visible.target.id}`);
        });
    }, {
        rootMargin: "-20% 0px -65% 0px",
        threshold: [0.12, 0.28, 0.5],
    });

    sections.forEach((section) => sectionObserver.observe(section));

    navLinks.forEach((link) => {
        link.addEventListener("click", () => {
            navLinks.forEach((item) => item.classList.remove("active"));
            link.classList.add("active");
        });
    });

    const photoInput = document.getElementById("foto");
    const avatarPreview = document.getElementById("avatarPreview");
    const previewPhotoCard = document.getElementById("previewPhotoCard");
    const nameInput = document.getElementById("nombre");
    const bioInput = document.getElementById("bio");
    const previewName = document.getElementById("previewName");
    const previewBio = document.getElementById("previewBio");
    const blockedUsersInput = document.getElementById("blockedUsersInput");
    const blockedList = document.getElementById("blockedList");

    if (photoInput) {
        photoInput.addEventListener("change", () => {
            const file = photoInput.files && photoInput.files[0];
            if (!file) return;

            if (!file.type.startsWith("image/")) {
                showToast("Selecciona una imagen valida.", "error");
                photoInput.value = "";
                return;
            }

            const url = URL.createObjectURL(file);
            [avatarPreview, previewPhotoCard].forEach((img) => {
                if (img) img.src = url;
            });
            showToast("Vista previa actualizada. Guarda para aplicar la foto.");
            markDirty("Foto lista para guardar.");
        });
    }

    if (nameInput && previewName) {
        nameInput.addEventListener("input", () => {
            previewName.textContent = nameInput.value.trim() || "Usuario";
            markDirty();
        });
    }

    if (bioInput && previewBio) {
        bioInput.addEventListener("input", () => {
            previewBio.textContent = bioInput.value.trim() || "Aficionado al cine, reseñas y estrenos.";
            markDirty();
        });
    }

    if (blockedUsersInput && blockedList) {
        blockedUsersInput.addEventListener("input", () => {
            renderBlockedUsers(blockedUsersInput.value, blockedList);
            markDirty();
        });
    }

    [profileForm, settingsForm].forEach((form) => {
        if (!form) return;
        form.addEventListener("change", () => markDirty());
        form.addEventListener("submit", () => {
            if (dirtyMessage) dirtyMessage.textContent = "Guardando cambios...";
            if (saveStatus) saveStatus.textContent = "Guardando...";
        });
    });

    if (deletePhotoButton && deletePhotoForm) {
        deletePhotoButton.addEventListener("click", () => {
            deletePhotoButton.disabled = true;
            deletePhotoButton.textContent = "Restableciendo...";
            deletePhotoForm.submit();
        });
    }

    function renderBlockedUsers(rawValue, container) {
        const users = rawValue
            .split(/[\n,]+/)
            .map((item) => item.trim())
            .filter(Boolean);

        container.innerHTML = "";
        if (!users.length) {
            const empty = document.createElement("em");
            empty.textContent = "No hay usuarios bloqueados.";
            container.appendChild(empty);
            return;
        }

        users.forEach((user) => {
            const chip = document.createElement("span");
            chip.textContent = user.startsWith("@") ? user : `@${user}`;
            container.appendChild(chip);
        });
    }

    function markDirty(text = "Hay cambios sin guardar.") {
        if (dirtyMessage) dirtyMessage.textContent = text;
        if (saveStatus) saveStatus.textContent = "Cambios pendientes";
    }

    function showToast(message, type = "success") {
        const toast = document.createElement("div");
        toast.className = `settings-toast ${type}`;
        toast.setAttribute("role", "status");
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }
});
