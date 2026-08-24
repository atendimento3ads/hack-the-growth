const CONFIG = {
  formEndpoint: "lead.php"
};

const MATERIALS = {
  n8n: {
    name: "Guia prático de n8n",
    url: "assets/pdfs/guia-pratico-n8n-3ads-2026.pdf"
  },
  claude: {
    name: "Guia prático de Claude",
    url: "assets/pdfs/guia-pratico-claude-3ads-2026.pdf"
  },
  chatgpt: {
    name: "Guia prático de ChatGPT",
    url: "assets/pdfs/guia-pratico-chatgpt-3ads-2026.pdf"
  },
  gemini: {
    name: "Guia prático de Gemini",
    url: "assets/pdfs/guia-pratico-gemini-3ads-2026.pdf"
  },
  notebooklm: {
    name: "Guia prático de NotebookLM",
    url: "assets/pdfs/guia-pratico-notebooklm-3ads-2026.pdf"
  }
};

const modal = document.querySelector("#download-modal");
const modalPanel = modal.querySelector(".modal-panel");
const formState = document.querySelector("#form-state");
const successState = document.querySelector("#success-state");
const form = document.querySelector("#lead-form");
const downloadLink = document.querySelector("#material-download");
const menuToggle = document.querySelector(".menu-toggle");
const navLinks = document.querySelector(".nav-links");
const menuToggleLabel = menuToggle.querySelector(".sr-only");
let previousFocus = null;
let selectedMaterial = null;

const lazyCovers = document.querySelectorAll(".lazy-cover[data-src]");
function loadCover(image) {
  if (!image.dataset.src) return;
  const revealCover = () => image.classList.add("is-loaded");
  image.addEventListener("load", revealCover, { once: true });
  image.loading = "eager";
  image.src = image.dataset.src;
  image.removeAttribute("data-src");
  if (image.complete) revealCover();
}

if ("IntersectionObserver" in window) {
  const coverObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      loadCover(entry.target);
      coverObserver.unobserve(entry.target);
    });
  }, { rootMargin: "240px 0px", threshold: .01 });
  lazyCovers.forEach(image => coverObserver.observe(image));
} else {
  lazyCovers.forEach(loadCover);
}

function openModal(materialId) {
  selectedMaterial = MATERIALS[materialId];
  if (!selectedMaterial) return;
  previousFocus = document.activeElement;
  form.reset();
  form.querySelectorAll(".field").forEach(field => field.classList.remove("has-error"));
  form.querySelectorAll("input").forEach(input => input.setAttribute("aria-invalid", "false"));
  form.querySelector(".consent").classList.remove("consent-wrap-error");
  const submitButton = form.querySelector("[type='submit']");
  submitButton.disabled = false;
  submitButton.querySelector("span").textContent = "Download";
  formState.hidden = false;
  successState.classList.remove("is-visible");
  document.querySelector("#selected-material").value = selectedMaterial.name;
  document.querySelector("#selected-material-name").textContent = selectedMaterial.name;
  document.querySelector("#modal-title").textContent = `Baixe o ${selectedMaterial.name}.`;
  modal.classList.add("is-open");
  modal.setAttribute("aria-hidden", "false");
  document.body.classList.add("modal-open");
  window.setTimeout(() => modal.querySelector("#name").focus(), 180);
}

function closeModal() {
  modal.classList.remove("is-open");
  modal.setAttribute("aria-hidden", "true");
  document.body.classList.remove("modal-open");
  if (previousFocus) previousFocus.focus();
}

document.querySelectorAll("[data-material-id]").forEach(button => {
  button.addEventListener("click", () => openModal(button.dataset.materialId));
});
document.querySelectorAll("[data-close-modal]").forEach(button => button.addEventListener("click", closeModal));

document.addEventListener("keydown", event => {
  if (event.key === "Escape" && modal.classList.contains("is-open")) closeModal();
  if (event.key !== "Tab" || !modal.classList.contains("is-open")) return;

  const focusable = [...modalPanel.querySelectorAll("button,a,input,select,textarea")].filter(el => !el.disabled && el.offsetParent !== null);
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
  if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
});

document.querySelectorAll(".faq-item button").forEach(button => {
  button.addEventListener("click", () => {
    const currentItem = button.closest(".faq-item");
    const willOpen = !currentItem.classList.contains("is-open");
    document.querySelectorAll(".faq-item").forEach(item => {
      item.classList.remove("is-open");
      item.querySelector("button").setAttribute("aria-expanded", "false");
    });
    if (willOpen) {
      currentItem.classList.add("is-open");
      button.setAttribute("aria-expanded", "true");
    }
  });
});

menuToggle.addEventListener("click", () => {
  const open = menuToggle.classList.toggle("is-open");
  navLinks.classList.toggle("is-open", open);
  menuToggle.setAttribute("aria-expanded", String(open));
  menuToggleLabel.textContent = open ? "Fechar menu" : "Abrir menu";
});

navLinks.querySelectorAll("a").forEach(link => link.addEventListener("click", () => {
  navLinks.classList.remove("is-open");
  menuToggle.classList.remove("is-open");
  menuToggle.setAttribute("aria-expanded", "false");
  menuToggleLabel.textContent = "Abrir menu";
}));

function validateForm() {
  let valid = true;
  form.querySelectorAll(".field").forEach(field => {
    const input = field.querySelector("input, select, textarea");
    const invalid = input.required && !input.checkValidity();
    field.classList.toggle("has-error", invalid);
    input.setAttribute("aria-invalid", String(invalid));
    if (invalid) valid = false;
  });
  const consent = form.querySelector("[name='consent']");
  const consentLabel = consent.closest(".consent");
  consentLabel.classList.toggle("consent-wrap-error", !consent.checked);
  consent.setAttribute("aria-invalid", String(!consent.checked));
  if (!consent.checked) valid = false;
  return valid;
}

form.querySelectorAll("input, select, textarea").forEach(input => input.addEventListener("input", () => {
  input.closest(".field")?.classList.remove("has-error");
  input.setAttribute("aria-invalid", "false");
  if (input.name === "consent") input.closest(".consent").classList.remove("consent-wrap-error");
}));

form.addEventListener("submit", async event => {
  event.preventDefault();
  if (!validateForm()) return;

  const submitButton = form.querySelector("[type='submit']");
  const originalLabel = submitButton.querySelector("span").textContent;
  const pdfTab = window.open("about:blank", "_blank");
  if (pdfTab) {
    pdfTab.document.title = "Preparando seu material...";
    pdfTab.document.body.innerHTML = '<p style="font:16px system-ui;padding:32px">Preparando seu material...</p>';
  }
  submitButton.disabled = true;
  submitButton.querySelector("span").textContent = "Liberando...";

  try {
    if (CONFIG.formEndpoint) {
      const leadData = new FormData(form);
      leadData.set("page_url", window.location.href);
      const response = await fetch(CONFIG.formEndpoint, {
        method: "POST",
        body: leadData,
        headers: { Accept: "application/json" }
      });
      let result = null;
      try {
        result = await response.json();
      } catch (parseError) {
        throw new Error("O servidor não respondeu corretamente. Confirme se o PHP está ativo na hospedagem.");
      }
      if (!response.ok || result.success !== true) {
        throw new Error(result.message || "Não foi possível enviar o formulário.");
      }
    } else {
      // Mantém a experiência navegável enquanto o endpoint final não foi configurado.
      await new Promise(resolve => window.setTimeout(resolve, 650));
    }

    downloadLink.href = selectedMaterial.url;
    if (pdfTab) {
      pdfTab.opener = null;
      pdfTab.location.href = selectedMaterial.url;
    }

    formState.hidden = true;
    successState.classList.add("is-visible");
  } catch (error) {
    if (pdfTab) pdfTab.close();
    submitButton.querySelector("span").textContent = "Tentar novamente";
    window.alert(error.message);
  } finally {
    submitButton.disabled = false;
    if (!successState.classList.contains("is-visible")) submitButton.querySelector("span").textContent = originalLabel;
  }
});

const revealElements = document.querySelectorAll(".reveal-on-scroll");
if ("IntersectionObserver" in window) {
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      }
    });
  }, { rootMargin: "0px 0px -8%", threshold: .08 });
  revealElements.forEach(element => observer.observe(element));
} else {
  revealElements.forEach(element => element.classList.add("is-visible"));
}

const sections = [...document.querySelectorAll("main section[id]")];
const menuAnchors = [...document.querySelectorAll(".nav-links a")];
if ("IntersectionObserver" in window) {
  const sectionObserver = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      menuAnchors.forEach(anchor => anchor.classList.toggle("active", anchor.getAttribute("href") === `#${entry.target.id}`));
    });
  }, { rootMargin: "-35% 0px -55%", threshold: 0 });
  sections.forEach(section => sectionObserver.observe(section));
}

document.querySelector("#year").textContent = new Date().getFullYear();
