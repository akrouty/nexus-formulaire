// === DOM references ===
const analyzeBtn = document.getElementById("analyzeBtn");
const userText = document.getElementById("userText");

const intentResult = document.getElementById("intentResult");

const dynamicForm = document.getElementById("dynamicForm");
const dynamicFieldsContainer = document.getElementById("dynamicFields");
const submitBtn = document.getElementById("submitBtn");
const formError = document.getElementById("formError");

const confirmationModal = document.getElementById("confirmationModal");
const confirmationText = document.getElementById("confirmationText");
const closeModalBtn = document.getElementById("closeModalBtn");

const aiLoading = document.getElementById("ai-loading");
const missionResult = document.getElementById("mission-result");
const missionLabel = document.getElementById("mission-label");

const errorToast = document.getElementById("errorToast");

let currentMission = null;
let typingTimerId = null;

function typeWriter(element, text, speed = 25) {
  if (!element) return;

  // on annule un éventuel précédent timer
  if (typingTimerId) {
    clearTimeout(typingTimerId);
    typingTimerId = null;
  }

  element.textContent = "";
  let index = 0;

  function step() {
    if (index < text.length) {
      element.textContent += text.charAt(index);
      index++;
      typingTimerId = setTimeout(step, speed);
    }
  }

  step();
}


// === Toast d'erreur ===
function showErrorToast(message) {
  if (!errorToast) return;
  errorToast.textContent = message;

  errorToast.classList.add("show");
  // on reset le timer précédent si besoin
  if (errorToast._hideTimeout) {
    clearTimeout(errorToast._hideTimeout);
  }
  errorToast._hideTimeout = setTimeout(() => {
    errorToast.classList.remove("show");
  }, 4000);
}

// === Utils ===
function clearForm() {
  dynamicFieldsContainer.innerHTML = "";
  formError.textContent = "";
  submitBtn.disabled = true;
}

function labelFromMission(mission) {
  switch (mission) {
    case "don":
      return "Offrir un don 💰";
    case "benevolat":
      return "Rejoindre la Guilde des Bénévoles 🛡️";
    case "infos":
      return "Demander des informations ❓";
    case "contact":
    default:
      return "Établir le contact 📞";
  }
}

// === Dynamic field creation ===
function createField(fieldDef) {
  const wrapper = document.createElement("div");
  wrapper.className = "field dynamic-field";

  const label = document.createElement("label");
  label.className = "label";
  label.htmlFor = fieldDef.name;
  label.textContent = fieldDef.label + (fieldDef.required ? " *" : "");
  wrapper.appendChild(label);

  if (fieldDef.type === "textarea") {
    const textarea = document.createElement("textarea");
    textarea.id = fieldDef.name;
    textarea.name = fieldDef.name;
    textarea.required = !!fieldDef.required;
    textarea.className = "input";
    wrapper.appendChild(textarea);
  } else if (fieldDef.type === "radio" && Array.isArray(fieldDef.options)) {
    const group = document.createElement("div");
    group.className = "radio-group";
    fieldDef.options.forEach((opt, idx) => {
      const optId = `${fieldDef.name}_${idx}`;
      const radioWrapper = document.createElement("div");
      radioWrapper.className = "badge-option";

      const radio = document.createElement("input");
      radio.type = "radio";
      radio.id = optId;
      radio.name = fieldDef.name;
      radio.value = opt;
      radio.required = !!fieldDef.required;

      const radioLabel = document.createElement("label");
      radioLabel.htmlFor = optId;
      radioLabel.textContent = opt;

      radioWrapper.appendChild(radio);
      radioWrapper.appendChild(radioLabel);
      group.appendChild(radioWrapper);
    });
    wrapper.appendChild(group);
  } else if (fieldDef.type === "select" && Array.isArray(fieldDef.options)) {
    const select = document.createElement("select");
    select.id = fieldDef.name;
    select.name = fieldDef.name;
    select.required = !!fieldDef.required;
    select.className = "input";

    const placeholder = document.createElement("option");
    placeholder.value = "";
    placeholder.textContent = "-- Sélectionner --";
    select.appendChild(placeholder);

    fieldDef.options.forEach((opt) => {
      const option = document.createElement("option");
      option.value = opt;
      option.textContent = opt;
      select.appendChild(option);
    });

    wrapper.appendChild(select);
  } else {
    const input = document.createElement("input");
    input.id = fieldDef.name;
    input.name = fieldDef.name;
    input.type = fieldDef.type || "text";
    input.required = !!fieldDef.required;
    input.className = "input";
    wrapper.appendChild(input);
  }

  return wrapper;
}

function buildFormFromDefinition(fields) {
  clearForm();

  fields.forEach((field, index) => {
    const fieldElement = createField(field);
    dynamicFieldsContainer.appendChild(fieldElement);

    // apparition progressive
    setTimeout(() => {
      fieldElement.classList.add("visible");
    }, index * 120);
  });

  submitBtn.disabled = false;
}

// === IA: call intention endpoint ===
async function callIntentionAPI(textOverride = "") {
  intentResult.textContent = "";
  clearForm();
  confirmationModal.classList.add("hidden");
  missionResult.classList.add("hidden");
  currentMission = null;

  const payload = {
    text: textOverride || userText.value || "",
    missionOverride: null,
  };

  if (!payload.text.trim()) {
    const msg =
      "Merci d'écrire une phrase pour décrire ton intention avant d'appeler l'IA.";
    intentResult.textContent = msg;
    showErrorToast(msg);
    return;
  }

  // montrer loader
  aiLoading.classList.remove("hidden");
  setTimeout(() => aiLoading.classList.add("visible"), 10);
  analyzeBtn.disabled = true;

  try {
    const res = await fetch(window.nexusRoutes.intention, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": window.csrfToken,
      },
      body: JSON.stringify(payload),
    });

    if (!res.ok) {
      throw new Error("Erreur lors de l'appel à l'analyse d'intention.");
    }

    const data = await res.json();
    currentMission = data.mission || null;

    if (!currentMission || !data.fields) {
      throw new Error(
        "L'IA n'a pas réussi à déterminer une mission valide pour ce texte."
      );
    }

    missionResult.classList.remove("hidden");
    missionLabel.textContent = labelFromMission(currentMission);

    buildFormFromDefinition(data.fields);
    intentResult.textContent = "";
  } catch (err) {
    console.error(err);
    const msg =
      err.message || "Impossible d'analyser l'intention pour le moment.";
    intentResult.textContent = msg;
    showErrorToast(msg);
    clearForm();
  } finally {
    aiLoading.classList.remove("visible");
    setTimeout(() => aiLoading.classList.add("hidden"), 300);
    analyzeBtn.disabled = false;
  }
}

// === Events ===
analyzeBtn.addEventListener("click", (e) => {
  e.preventDefault();
  callIntentionAPI(userText.value || "");
});

dynamicForm.addEventListener("submit", async (e) => {
  e.preventDefault();
  formError.textContent = "";

  if (!currentMission) {
    const msg =
      "Merci de laisser l'IA détecter ta mission avant d'envoyer le formulaire.";
    formError.textContent = msg;
    showErrorToast(msg);
    return;
  }

  const formData = new FormData(dynamicForm);
  const dataObj = {};
  formData.forEach((value, key) => {
    dataObj[key] = value.toString();
  });

  // 1) Validation côté front : champs obligatoires
  const requiredInputs = dynamicFieldsContainer.querySelectorAll("[required]");
  for (const input of requiredInputs) {
    const isRadio = input.type === "radio";
    const isEmptyRadio =
      isRadio && !dynamicForm.querySelector(`input[name="${input.name}"]:checked`);
    const isEmptyOther = !isRadio && !input.value.trim();

    if (isEmptyRadio || isEmptyOther) {
      const msg =
        "Merci de remplir tous les champs obligatoires (*) avant de valider.";
      formError.textContent = msg;
      showErrorToast(msg);

      const wrapper = input.closest(".field") || input;
      wrapper.classList.add("input-error");
      setTimeout(() => wrapper.classList.remove("input-error"), 300);

      return; 
    }
  }

  // 2) Validation e-mail côté front
  const emailInput = dynamicFieldsContainer.querySelector('input[type="email"]');
  if (emailInput) {
    const email = emailInput.value.trim();

    // petit regex simple pour format d'email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    if (!emailRegex.test(email)) {
      const msg = "Merci de saisir une adresse e-mail valide.";
      formError.textContent = msg;
      showErrorToast(msg);

      const wrapper = emailInput.closest(".field") || emailInput;
      wrapper.classList.add("input-error");
      setTimeout(() => wrapper.classList.remove("input-error"), 300);

      return; // ⛔ pas d'appel backend, donc captcha pas consommé
    }
  }

  // 3) Tout est OK → on envoie au backend
  try {
    submitBtn.disabled = true;

    const res = await fetch(window.nexusRoutes.submit, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": window.csrfToken,
      },
      body: JSON.stringify({
        mission: currentMission,
        data: dataObj,
      }),
    });

    if (!res.ok) {
      const errPayload = await res.json().catch(() => ({}));

      if (res.status === 422 && errPayload.details) {
        let msg = "Merci de corriger les champs suivants :\n";
        Object.entries(errPayload.details).forEach(([field, messages]) => {
          msg += `- ${field.replace("data.", "")}: ${messages.join(", ")}\n`;
        });
        throw new Error(msg);
      }

      const msg = errPayload.error || "Erreur lors de l'envoi du formulaire.";
      throw new Error(msg);
    }

    const payload = await res.json();
    const message = payload.message || "Merci pour votre contribution !";

// on ouvre la modale d'abord
confirmationModal.classList.remove("hidden");

// puis on lance l'effet "tapeur de texte"
typeWriter(confirmationText, message, 25);


    dynamicForm.reset();
    clearForm();
    currentMission = null;
    userText.value = "";
    missionResult.classList.add("hidden");
  } catch (err) {
    console.error(err);
    const msg =
      err.message || "Une erreur est survenue. Merci de réessayer.";
    formError.textContent = msg;
    showErrorToast(msg);
  } finally {
    submitBtn.disabled = false;
  }
  if (typeof grecaptcha !== "undefined") {
  grecaptcha.reset();
}
});


// === Modal close handlers ===
if (closeModalBtn) {
  closeModalBtn.addEventListener("click", () => {
    confirmationModal.classList.add("hidden");
  });
}

if (confirmationModal) {
  confirmationModal.addEventListener("click", (e) => {
    if (
      e.target === confirmationModal ||
      e.target.classList.contains("modal-backdrop")
    ) {
      confirmationModal.classList.add("hidden");
    }
  });
}

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && !confirmationModal.classList.contains("hidden")) {
    confirmationModal.classList.add("hidden");
  }
});
