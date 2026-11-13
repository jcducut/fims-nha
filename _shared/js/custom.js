(function () {
  "use strict";

  // Prevent multiple initializations
  if (window.phpmakerScriptInitialized) return;
  window.phpmakerScriptInitialized = true;

  let currentMode = "light";
  let currentTheme = "blue";
  let initializationComplete = false;

  // Complete theme definitions — aligned with liquidation-reimbursement-dashboard palette
  const themes = {
    blue: {
      light: {
        "--body": "#f8fafc",
        "--navbar-border": "3px solid #0f2045",
        "--navbar": "#ffffff",
        "--main-sidebar": "#292828",
        "--metrics-card": "linear-gradient(135deg, #0f2045 0%, #1e3a6e 100%)",
        "--metrics-card-text": "#ffffff",
        "--metrics-card-btn-text": "#ffffff",
        "--metric-card-icon-bg": "#ffffff",
        "--metric-card-icon": "#0f2045",
        "--card-wrapper": "#ffffff",
        "--card-border": "#e2e8f0",
        "--user-container-card": "rgba(248, 250, 252, 0.2)",
        "--menu-focus": "#0f2045",
        "--menu-focus-treeview": "rgba(15, 32, 69, 0.5)",
        "--menu-focus-text": "#d97706",
        "--menu-list": "#ffffff",
        "--icon-menu": "#1e3a6e",
        "--title-font": "#475569",
        "--btn-text": "#0f172a",
        "--btn-bg": "#e2e8f0",
        "--btn-hover": "#0f2045",
        "--btn-hover-text": "#ffffff",
        "--btn-primary": "#1e3a6e",
        "--btn-info": "#0f2045",
        "--btn-success": "#16a34a",
        "--btn-warning": "#dc2626",
        "--btn-hover-primary": "#0f2045",
        "--btn-hover-info": "#060f1f",
        "--btn-hover-success": "#15803d",
        "--btn-hover-warning": "#991b1b",
        "--add-btn-bg": "#0f2045",
        "--add-btn-hover": "#1e3a6e",
        "--view-btn-bg": "#1e3a6e",
        "--view-btn-hover": "#0f2045",
        "--edit-btn-bg": "#0ea5e9",
        "--edit-btn-hover": "#0284c7",
        "--delete-btn-bg": "#dc2626",
        "--delete-btn-hover": "#991b1b",
        "--table-header": "#0f2045",
        "--table-footer-bg": "#f1f5f9",
      },
      dark: {
        "--body": "#0f172a",
        "--navbar": "#1e293b",
        "--main-sidebar": "rgba(28, 31, 34, 1)",
        "--metrics-card": "rgba(30, 58, 110, 0.4)",
        "--metrics-card-text": "#ffffff",
        "--metrics-card-btn-text": "#ffffff",
        "--metric-card-icon-bg": "#ffffff",
        "--metric-card-icon": "#0f2045",
        "--card-wrapper": "#1e293b",
        "--card-border": "#334155",
        "--user-container-card": "rgba(15, 32, 69, 0.6)",
        "--menu-focus": "#0f2045",
        "--menu-focus-treeview": "rgba(15, 32, 69, 0.5)",
        "--menu-focus-text": "#d97706",
        "--menu-list": "#ffffff",
        "--icon-menu": "#94a3b8",
        "--title-font": "#94a3b8",
        "--btn-text": "#f1f5f9",
        "--btn-bg": "rgba(226, 232, 240, 0.15)",
        "--btn-hover": "#1e3a6e",
        "--btn-hover-text": "#ffffff",
        "--btn-primary": "#1e3a6e",
        "--btn-info": "#0f2045",
        "--btn-success": "#16a34a",
        "--btn-warning": "#dc2626",
        "--btn-hover-primary": "#0f2045",
        "--btn-hover-info": "#060f1f",
        "--btn-hover-success": "#15803d",
        "--btn-hover-warning": "#991b1b",
        "--add-btn-bg": "#0f2045",
        "--add-btn-hover": "#1e3a6e",
        "--view-btn-bg": "#1e3a6e",
        "--view-btn-hover": "#0f2045",
        "--edit-btn-bg": "#0ea5e9",
        "--edit-btn-hover": "#0284c7",
        "--delete-btn-bg": "#dc2626",
        "--delete-btn-hover": "#991b1b",
        "--table-header": "#0f2045",
        "--table-footer-bg": "rgba(226, 232, 240, 0.1)",
      },
    },
  };

  // Chart colors — aligned with dashboard palette
  const chartColors = {
    blue: {
      light: {
        borderColor: ["rgba(15, 32, 69, 0)"],
        gridColor: "rgba(226, 232, 240, 1)",
        textColor: "#475569",
      },
      dark: {
        borderColor: ["rgba(30, 58, 110, 0)"],
        gridColor: "rgba(51, 65, 85, 1)",
        textColor: "#94a3b8",
      },
    },
  };

  // SINGLE window load listener instead of multiple
  function handleWindowLoad() {
    if (initializationComplete) return;
    initializationComplete = true;

    // Batch all DOM manipulations
    performDOMOperations();
    createCollapsibleControls();
    setupEventListeners();
    setupCollapsibleEventListeners();
    loadSavedPreferences();

    console.log("PHPMaker script initialized once");
  }

  // All DOM operations in one function to avoid multiple reflows
  function performDOMOperations() {
    if (typeof jQuery === "undefined") return;

    // Reorganize search elements
    const searchElement = jQuery(".ew-form.ew-ext-search-form");
    const mainContentElement = jQuery(
      "body > div.wrapper.ew-layout > div.content-wrapper > section > div > main"
    );

    if (searchElement.length && mainContentElement.length) {
      const wrapperDiv = jQuery("<div>", { class: "search-container" });
      searchElement.detach();
      mainContentElement.detach();
      wrapperDiv.append(searchElement, mainContentElement);
      jQuery(
        "body > div.wrapper.ew-layout > div.content-wrapper > section > div"
      ).append(wrapperDiv);
    }

    // Setup toolbar elements
    const toolbarElement = jQuery(
      "body > div.wrapper.ew-layout > div.content-wrapper > section > div > div.btn-toolbar.ew-toolbar"
    );
    const mainElement = jQuery(
      "body > div.wrapper.ew-layout > div.content-wrapper > section > div > main"
    );

    if (toolbarElement.length && mainElement.length) {
      const containerDiv = jQuery("<div>", {
        class: "custom-content-container",
      });
      toolbarElement.detach();
      mainElement.detach();
      containerDiv.append(toolbarElement, mainElement);
      jQuery(
        "body > div.wrapper.ew-layout > div.content-wrapper > section > div"
      ).append(containerDiv);
    }

    // Wrap info icons
    const iconSpans = jQuery("span.info-box-icon");
    if (iconSpans.length) {
      iconSpans.wrap("<div class='icon-wrapper'></div>");
    }

    // Wrap elements
    const element1 = document.querySelector(
      "div.search-container > form > div > div > div.col-sm-auto.px-0.pe-sm-2"
    );
    const element2 = document.querySelector(
      "div.search-container > form > div > div > div.col-sm-auto.mb-3"
    );
    const parentContainer = document.querySelector(
      "div.search-container > form > div > div"
    );

    if (element1 && element2 && parentContainer) {
      const wrapperDiv = document.createElement("div");
      wrapperDiv.className = "elements-wrapper";
      wrapperDiv.appendChild(element1);
      wrapperDiv.appendChild(element2);
      parentContainer.appendChild(wrapperDiv);
    }

    // Initial button symbols
    updateButtonSymbols();
  }

  // Function to update button symbols (debounced)
  let buttonUpdateTimeout;
  function updateButtonSymbols() {
    clearTimeout(buttonUpdateTimeout);
    buttonUpdateTimeout = setTimeout(() => {
      const addElement = document.querySelector(
        "body > div.wrapper.ew-layout > div.content-wrapper > section > div > div.custom-content-container > div > div.ew-action-option.d-inline-block.text-nowrap > div > a.ew-add"
      );

      const editElement = document.querySelector(
        "body > div.wrapper.ew-layout > div.content-wrapper > section > div > div.custom-content-container > div > div.ew-action-option.d-inline-block.text-nowrap > div > a.ew-edit"
      );

      const deleteElement = document.querySelector(
        "body > div.wrapper.ew-layout > div.content-wrapper > section > div > div.custom-content-container > div > div.ew-action-option.d-inline-block.text-nowrap > div > a.ew-delete"
      );

      const copyElement = document.querySelector(
        "body > div.wrapper.ew-layout > div.content-wrapper > section > div > div.custom-content-container > div > div.ew-action-option.d-inline-block.text-nowrap > div > a.ew-copy"
      );

      if (addElement || editElement || deleteElement || copyElement) {
        addElement.textContent = "✚ " + addElement.textContent;
        editElement.textContent = "✏️ " + editElement.textContent;
        deleteElement.textContent = "🗑️ " + deleteElement.textContent;
        copyElement.textContent = "📋 " + copyElement.textContent;
      }

      const sourceElement = document.querySelector("#ew-list > div > div > div.ew-list-other-options");
      const targetDiv = document.querySelector("div.search-container > form > div > div");
      
      if (sourceElement && targetDiv) {
        if (!targetDiv.contains(sourceElement)) {
          targetDiv.appendChild(sourceElement);
          console.log("Successfully moved the element to the target div");
        }
      }
      
      const element = document.querySelector("div.ew-list-other-options > div > div > a.ew-add");
      const element2 = document.querySelector("#femployeesrch_search_panel > div > div.ew-list-other-options > div.ew-detail-option.d-inline-block.text-nowrap > div > a.ew-detail-add");
      
      if (element && !element.textContent.includes("✚")) {
        element.textContent = "✚ " + element.textContent;
        console.log("Added symbol to first button");
      }
      
      if (element2 && !element2.textContent.includes("✚")) {
        element2.textContent = "✚ " + element2.textContent;
        console.log("Added symbol to second button");
      }

    }, 100);
  }

  // SINGLE mutation observer instead of multiple
  let observer;
  function setupMutationObserver() {
    if (observer) return; // Prevent multiple observers

    observer = new MutationObserver(function (mutations) {
      let needsUpdate = false;

      for (const mutation of mutations) {
        if (mutation.type === "childList" && mutation.addedNodes.length > 0) {
          for (const node of mutation.addedNodes) {
            if (node.nodeType === Node.ELEMENT_NODE) {
              if (
                (node.matches &&
                  node.matches(
                    "a.ew-add, a.ew-edit, a.ew-delete, a.ew-copy"
                  )) ||
                (node.querySelector &&
                  node.querySelector(
                    "a.ew-add, a.ew-edit, a.ew-delete, a.ew-copy"
                  ))
              ) {
                needsUpdate = true;
                break;
              }
            }
          }
        }
        if (needsUpdate) break;
      }

      if (needsUpdate) {
        updateButtonSymbols();
      }
    });

    observer.observe(document.body, { childList: true, subtree: true });
  }

  // Create collapsible controls (replaces createThemeControls and createFontControls)
  function createCollapsibleControls() {
    if (document.getElementById('controls-container')) return;

    const controlsHTML = `
        <button id="controls-toggle" title="Settings">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
        </button>
        
        <div id="controls-container">
            <div class="controls-section">
                <h4>Theme Settings</h4>
                <div style="margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span>Mode</span>
                        <label class="theme-switch" style="background: grey; border-radius: 20px; position: relative; display: inline-block; width: 50px; height: 24px;">
                            <input type="checkbox" id="theme-mode-toggle" style="opacity: 0; width: 0; height: 0;">
                            <span class="slider round" style="position: absolute; cursor: pointer; top: 0; left: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 34px;">
                                <span style="position: absolute; content: ''; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%;"></span>
                            </span>
                        </label>
                        <span id="mode-label">Light</span>
                    </div>
                </div>
            </div>
            
            <div class="controls-section">
                <h4>Font Size Settings</h4>
                <div style="margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <span style="font-weight: bold;">All</span>
                        <span id="body-percentage" class="size-percentage">100%</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button class="font-size-btn" data-target="body" data-action="decrease" style="width: 30px; height: 30px; border-radius: 5px; border: none; background: #f0f0f0; cursor: pointer;">-</button>
                        <input type="range" id="body-slider" min="70" max="150" value="100" style="flex-grow: 1;">
                        <button class="font-size-btn" data-target="body" data-action="increase" style="width: 30px; height: 30px; border-radius: 5px; border: none; background: #f0f0f0; cursor: pointer;">+</button>
                    </div>
                </div>
                <div style="margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <span style="font-weight: bold;">Button</span>
                        <span id="button-percentage" class="size-percentage">100%</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button class="font-size-btn" data-target="button" data-action="decrease" style="width: 30px; height: 30px; border-radius: 5px; border: none; background: #f0f0f0; cursor: pointer;">-</button>
                        <input type="range" id="button-slider" min="70" max="150" value="100" style="flex-grow: 1;">
                        <button class="font-size-btn" data-target="button" data-action="increase" style="width: 30px; height: 30px; border-radius: 5px; border: none; background: #f0f0f0; cursor: pointer;">+</button>
                    </div>
                </div>
                <div style="margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <span style="font-weight: bold;">Input Fields</span>
                        <span id="input-percentage" class="size-percentage">100%</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button class="font-size-btn" data-target="input" data-action="decrease" style="width: 30px; height: 30px; border-radius: 5px; border: none; background: #f0f0f0; cursor: pointer;">-</button>
                        <input type="range" id="input-slider" min="70" max="150" value="100" style="flex-grow: 1;">
                        <button class="font-size-btn" data-target="input" data-action="increase" style="width: 30px; height: 30px; border-radius: 5px; border: none; background: #f0f0f0; cursor: pointer;">+</button>
                    </div>
                </div>
                <div style="margin-bottom: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                        <span style="font-weight: bold;">Table</span>
                        <span id="td-percentage" class="size-percentage">100%</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button class="font-size-btn" data-target="td" data-action="decrease" style="width: 30px; height: 30px; border-radius: 5px; border: none; background: #f0f0f0; cursor: pointer;">-</button>
                        <input type="range" id="td-slider" min="70" max="150" value="100" style="flex-grow: 1;">
                        <button class="font-size-btn" data-target="td" data-action="increase" style="width: 30px; height: 30px; border-radius: 5px; border: none; background: #f0f0f0; cursor: pointer;">+</button>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 15px;">
                    <button id="reset-all-fonts" style="padding: 8px 20px; background: #5e35b1; color: white; border: none; border-radius: 5px; cursor: pointer;">Reset All</button>
                </div>
            </div>
        </div>
    `;

    document.body.insertAdjacentHTML('beforeend', controlsHTML);
  }

  // SINGLE event listener setup instead of multiple
  function setupEventListeners() {
    // Single click handler for all elements
    document.addEventListener("click", function (event) {
      const target = event.target;

      // Theme controls
      if (target.matches(".color-theme-btn")) {
        handleThemeChange(target);
      }

      // Font controls
      else if (target.matches(".font-size-btn")) {
        handleFontSizeChange(target);
      } else if (target.id === "reset-all-fonts") {
        resetAllFonts();
      }
    });

    // Single change handler
    document.addEventListener("change", function (event) {
      if (event.target.id === "theme-mode-toggle") {
        handleModeChange(event.target);
      } else if (event.target.matches('select[name="pagelen"]')) {
        setTimeout(updateButtonSymbols, 500);
      }
    });

    // Single input handler for font sliders
    document.addEventListener("input", function (event) {
      if (event.target.matches('input[type="range"][id$="-slider"]')) {
        handleFontSliderChange(event.target);
      }
    });

    // Setup mutation observer
    setupMutationObserver();
  }

  // Setup collapsible event listeners
  function setupCollapsibleEventListeners() {
    document.addEventListener('click', function(event) {
      const target = event.target;
      
      // Toggle controls panel
      if (target.id === 'controls-toggle' || target.closest('#controls-toggle')) {
        const container = document.getElementById('controls-container');
        const toggle = document.getElementById('controls-toggle');
        
        container.classList.toggle('expanded');
        toggle.classList.toggle('expanded');
      }
      
      // Close panel when clicking outside
      else if (!target.closest('#controls-container') && !target.closest('#controls-toggle')) {
        const container = document.getElementById('controls-container');
        const toggle = document.getElementById('controls-toggle');
        
        if (container.classList.contains('expanded')) {
          container.classList.remove('expanded');
          toggle.classList.remove('expanded');
        }
      }
    });
  }

  // Theme functions
  function applyTheme(theme, mode) {
    if (!themes[theme] || !themes[theme][mode]) {
      theme = "blue";
      mode = mode === "dark" ? "dark" : "light";
    }
    const selectedTheme = themes[theme][mode];

    for (const property in selectedTheme) {
      document.documentElement.style.setProperty(
        property,
        selectedTheme[property]
      );
    }

    const sidebar = document.querySelector(
      "body > div.wrapper.ew-layout > aside"
    );
    if (sidebar) {
      sidebar.style.background = selectedTheme["--main-sidebar"];
    }

    // Update chart colors
    applyChartTheme(theme, mode);

    // Save preferences
    localStorage.setItem("phpmaker-theme", theme);
    localStorage.setItem("phpmaker-mode", mode);
  }

  function applyChartTheme(theme, mode) {
    if (typeof Chart !== "undefined") {
      const colors = chartColors[theme][mode];

      // Update Chart.js defaults
      if (Chart.defaults) {
        if (Chart.defaults.color !== undefined) {
          Chart.defaults.color = colors.textColor;
        }

        if (Chart.defaults.plugins && Chart.defaults.plugins.legend) {
          Chart.defaults.plugins.legend.labels =
            Chart.defaults.plugins.legend.labels || {};
          Chart.defaults.plugins.legend.labels.color = colors.textColor;
        }
      }

      // Update existing charts
      if (Chart.instances) {
        Object.values(Chart.instances).forEach((chart) => {
          if (
            chart.config &&
            chart.config.options &&
            chart.config.options.scales
          ) {
            Object.keys(chart.config.options.scales).forEach((scaleKey) => {
              const scale = chart.config.options.scales[scaleKey];
              if (scale.grid) scale.grid.color = colors.gridColor;
              if (scale.ticks) scale.ticks.color = colors.textColor;
            });
          }
          chart.update();
        });
      }
    }
  }

  function handleThemeChange(button) {
    document.querySelectorAll(".color-theme-btn").forEach((btn) => {
      btn.classList.remove("active");
      btn.style.outline = "none";
    });

    button.classList.add("active");
    button.style.outline = "3px solid #5e35b1";
    button.style.outlineOffset = "2px";

    currentTheme = button.dataset.theme;
    applyTheme(currentTheme, currentMode);
  }

  // One source of truth: PHPMaker's own colour-mode switcher (localStorage "theme" + ew.setTheme).
  // Both the navbar dropdown and this panel go through applyMode(), and each reacts to the
  // "changetheme" event that ew.setTheme() fires, so they never disagree.
  function applyMode(mode) {
    mode = mode === "dark" ? "dark" : "light";
    currentMode = mode;
    document.documentElement.setAttribute("data-bs-theme", mode);
    const toggle = document.getElementById("theme-mode-toggle");
    const label = document.getElementById("mode-label");
    if (toggle) toggle.checked = mode === "dark";
    if (label) label.textContent = mode === "dark" ? "Dark" : "Light";
    applyTheme(currentTheme, mode);
  }

  function handleModeChange(toggle) {
    const mode = toggle.checked ? "dark" : "light";
    if (window.ew && ew.setStoredTheme && ew.setTheme) {
      ew.setStoredTheme(mode);          // same key the navbar dropdown uses
      ew.setTheme(mode);                // fires "changetheme" -> applyMode()
      if (ew.showActiveTheme) ew.showActiveTheme(mode); // highlight Light/Dark in the navbar dropdown
    }
    applyMode(mode);
  }

  // Navbar dropdown (or anything else) changed the mode -> follow it.
  document.addEventListener("changetheme", (e) => {
    if (initializationComplete) applyMode(e.detail);
  });

  // Font functions
  function handleFontSizeChange(button) {
    const target = button.dataset.target;
    const action = button.dataset.action;
    const slider = document.getElementById(`${target}-slider`);
    const currentVal = parseInt(slider.value);
    const newVal =
      action === "increase"
        ? Math.min(currentVal + 5, 150)
        : Math.max(currentVal - 5, 70);

    slider.value = newVal;
    handleFontSliderChange(slider);
  }

  function handleFontSliderChange(slider) {
    const target = slider.id.replace("-slider", "");
    const value = slider.value;
    const percentage = document.getElementById(`${target}-percentage`);

    if (percentage) {
      percentage.textContent = `${value}%`;
    }

    document.documentElement.style.setProperty(
      `--${target}-scale`,
      value / 100
    );
    localStorage.setItem(`phpmaker-font-${target}`, value);
  }

  function resetAllFonts() {
    const tagTypes = ["body", "button", "input", "td"];
    tagTypes.forEach((tag) => {
      const slider = document.getElementById(`${tag}-slider`);
      const percentage = document.getElementById(`${tag}-percentage`);

      if (slider) slider.value = 100;
      if (percentage) percentage.textContent = "100%";

      document.documentElement.style.setProperty(`--${tag}-scale`, 1);
      localStorage.removeItem(`phpmaker-font-${tag}`);
    });
  }

  function loadSavedPreferences() {
    // Load theme preferences
    let savedTheme = localStorage.getItem("phpmaker-theme");
    if (!themes[savedTheme]) savedTheme = "blue";   // empty/stale value -> the only defined theme
    // Light is the default; dark only when the user switched it (saved in localStorage).
    let savedMode = localStorage.getItem("theme") || localStorage.getItem("phpmaker-mode");
    if (savedMode !== "light" && savedMode !== "dark") savedMode = "light";
    // Nothing chosen yet: make PHPMaker's switcher agree with our light default (it would follow the OS).
    if (!localStorage.getItem("theme") && window.ew && ew.setTheme) {
      ew.setTheme(savedMode);
      if (ew.showActiveTheme) ew.showActiveTheme(savedMode);
    }

    currentTheme = savedTheme;
    currentMode = savedMode;

    // Apply theme
    applyTheme(savedTheme, savedMode);

    // Update UI
    const modeToggle = document.getElementById("theme-mode-toggle");
    const modeLabel = document.getElementById("mode-label");

    if (modeToggle && modeLabel) {
      modeToggle.checked = savedMode === "dark";
      modeLabel.textContent = savedMode === "dark" ? "Dark" : "Light";
      document.documentElement.setAttribute("data-bs-theme", savedMode);
    }

    // Update theme button
    const themeButton = document.querySelector(
      `.color-theme-btn[data-theme="${savedTheme}"]`
    );
    if (themeButton) {
      themeButton.classList.add("active");
      themeButton.style.outline = "3px solid #5e35b1";
      themeButton.style.outlineOffset = "2px";
    }

    // Load font preferences
    const tagTypes = ["body", "button", "input", "td"];
    tagTypes.forEach((tag) => {
      const savedValue = localStorage.getItem(`phpmaker-font-${tag}`);
      if (savedValue) {
        const slider = document.getElementById(`${tag}-slider`);
        const percentage = document.getElementById(`${tag}-percentage`);

        if (slider) slider.value = savedValue;
        if (percentage) percentage.textContent = `${savedValue}%`;

        document.documentElement.style.setProperty(
          `--${tag}-scale`,
          savedValue / 100
        );
      }
    });
  }

  // Inject CSS once
  function injectCSS() {
    if (document.getElementById("phpmaker-styles")) return;

    // IBM Plex Sans — matches dashboard typography
    if (!document.getElementById("phpmaker-ibmfont")) {
      const link = document.createElement("link");
      link.id = "phpmaker-ibmfont";
      link.rel = "stylesheet";
      link.href = "https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap";
      document.head.appendChild(link);
    }

    const style = document.createElement("style");
    style.id = "phpmaker-styles";
    style.textContent = `
            :root {
                --body-scale: 1;
                --button-scale: 1;
                --input-scale: 1;
                --td-scale: 1;
            }

            body, button, input, select, textarea, td, th {
                font-family: "IBM Plex Sans", sans-serif !important;
            }
            .mono, code, pre { font-family: "IBM Plex Mono", monospace !important; }

            body { font-size: calc(16px * var(--body-scale)) !important; }
            button:not(.font-size-btn):not(#controls-toggle):not(#reset-all-fonts):not(.accordion-button),
            .btn { font-size: calc(1em * var(--button-scale)) !important; }
            /* accordion headers are <button>s inside <h2>; 1em would inherit the heading size */
            .accordion-button { font-size: calc(0.875rem * var(--button-scale)) !important; }
            input, select, textarea { font-size: calc(1em * var(--input-scale)) !important; }
            td, th { font-size: calc(1em * var(--td-scale)) !important; }
            
            #theme-mode-toggle:checked + .slider {
                background-color: #5e35b1;
            }
            
            #theme-mode-toggle:checked + .slider span {
                transform: translateX(26px);
            }
            
            .color-theme-btn.active {
                outline: 3px solid #5e35b1 !important;
                outline-offset: 2px !important;
            }
            
            [data-bs-theme="dark"] .chartjs-legend li span,
            [data-bs-theme="dark"] canvas + ul li span {
                color: #ffffff !important;
            }
            
            [data-bs-theme="light"] .chartjs-legend li span,
            [data-bs-theme="light"] canvas + ul li span {
                color: #333333 !important;
            }

            /* Collapsible panel styles */
            #controls-container {
                position: fixed;
                bottom: 20px;
                right: -350px;
                width: 320px;
                background: white;
                border-radius: 10px 0 0 10px;
                box-shadow: -4px 0 12px rgba(0,0,0,0.15);
                z-index: 1001;
                transition: right 0.3s ease;
                padding: 20px;
                max-height: 80vh;
                overflow-y: auto;
            }

            #controls-container.expanded {
                right: 0;
            }

            #controls-toggle {
                position: fixed;
                bottom: 150px;
                right: 0;
                width: 40px;
                height: 50px;
                background: #0f2045;
                color: white;
                border: none;
                border-radius: 10px 0 0 10px;
                box-shadow: -4px 0 12px rgba(0,0,0,0.15);
                z-index: 100;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.3s ease;
            }


            #controls-toggle.expanded {
                right: 320px;
            }

            #controls-toggle svg {
                transition: transform 0.3s ease;
            }

            #controls-toggle.expanded svg {
                transform: rotate(180deg);
            }

            [data-bs-theme="dark"] #controls-container {
                background: #2c2c2c;
                color: white;
            }

            .controls-section {
                margin-bottom: 25px;
                padding-bottom: 20px;
                border-bottom: 1px solid #e0e0e0;
            }

            [data-bs-theme="dark"] .controls-section {
                border-bottom-color: #444;
            }

            .controls-section:last-child {
                border-bottom: none;
            }

            .controls-section h4 {
                margin-top: 0;
                margin-bottom: 15px;
                font-size: 16px;
                font-weight: bold;
            }

            [data-bs-theme="dark"] .font-size-btn {
                background: #444 !important;
                color: white;
            }

            /* ── Info-box / metrics cards — dashboard KPI style ── */
            .info-box {
                background: #ffffff !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 12px !important;
                box-shadow: 0 1px 3px rgba(0,0,0,.05) !important;
                overflow: visible !important;
                min-height: unset !important;
                display: flex !important;
                align-items: center !important;
                padding: 12px !important;
                gap: 12px !important;
                flex-wrap: nowrap !important;
            }
            [data-bs-theme="dark"] .info-box {
                background: #1e293b !important;
                border-color: #334155 !important;
            }

            .icon-wrapper,
            .info-box-icon {
                background: linear-gradient(135deg, #0f2045 0%, #1e3a6e 100%) !important;
                border-radius: 10px !important;
                width: 52px !important;
                min-width: 52px !important;
                height: 52px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                margin: 0 !important;
                font-size: 22px !important;
                color: #ffffff !important;
                flex-shrink: 0 !important;
                line-height: 1 !important;
            }
            .info-box-icon img,
            .info-box-icon i,
            .info-box-icon svg {
                filter: brightness(0) invert(1);
            }

            .info-box-content {
                padding: 0 !important;
                flex: 1 !important;
                min-width: 0 !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
            }
            .info-box-text {
                font-size: 11px !important;
                font-weight: 600 !important;
                color: #475569 !important;
                text-transform: uppercase !important;
                letter-spacing: .4px !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                line-height: 1.3 !important;
            }
            [data-bs-theme="dark"] .info-box-text {
                color: #94a3b8 !important;
            }
            .info-box-number {
                font-size: 24px !important;
                font-weight: 700 !important;
                color: #0f2045 !important;
                line-height: 1.2 !important;
                white-space: nowrap !important;
                margin-top: 2px !important;
            }
            [data-bs-theme="dark"] .info-box-number {
                color: #f1f5f9 !important;
            }
            .progress-description,
            .info-box-more {
                font-size: 9px !important;
                color: #94a3b8 !important;
                white-space: nowrap !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                margin-top: 1px !important;
            }

            /* ── Table overflow fix — compress and scroll ── */
            .content-wrapper,
            .content-wrapper section,
            .content-wrapper section > div {
                overflow: visible !important;
            }
            .custom-content-container,
            .search-container {
                overflow: visible !important;
            }
            main.ew-main,
            main {
                overflow-x: auto !important;
                overflow-y: visible !important;
            }
            .ew-table-responsive,
            .table-responsive,
            [class*="ew-list"] {
                overflow-x: auto !important;
            }

            /* ── Compact table layout ── */
            .ew-table,
            table.table {
                table-layout: auto !important;
                width: 100% !important;
                font-size: calc(12px * var(--td-scale)) !important;
            }
            .ew-table td,
            .ew-table th,
            table.table td,
            table.table th {
                padding: 6px 8px !important;
                vertical-align: middle !important;
                white-space: nowrap !important;
                font-size: calc(15px * var(--td-scale)) !important;
            }
            /* Allow description-style columns to wrap */
            .ew-table td:nth-child(4),
            table.table td:nth-child(4) {
                white-space: normal !important;
                min-width: 120px !important;
                max-width: 180px !important;
                word-break: break-word !important;
            }
            /* Action buttons — tighten spacing */
            .ew-table td .btn,
            table.table td .btn,
            .ew-table td a.btn,
            table.table td a.btn {
                padding: 3px 7px !important;
                font-size: 14px !important;
                margin: 1px !important;
            }
            /* Make the table wrapper scroll when still too wide */
            #ew-list,
            .ew-list-table-responsive {
                overflow-x: auto !important;
                width: 100% !important;
            }
        `;

    document.head.appendChild(style);
  }

  // AFIMS palette + UI (custom.css) is rolled out one module at a time.
  // UAC and DOCUMENT load custom.css through STYLESHEET_FILES; the modules listed
  // here get it from this script instead, so PHPMaker-generated config files and
  // the shared fmd.css stay untouched. Add a module only when it is converted.
  // See _guidelines/ui-modernization-guide.md
  const AFIMS_UI_MODULES = ["ACCOUNTING", "ACCOUNTING-BAPD", "GSD", "ACCOUNTING-DCD", "ACCOUNTING-GAD", "FMD", "TREASURY", "REGIONAL"];

  // AFIMS dark palette is rolled out one module at a time, like the UI above.
  // Listed modules get html.afims-dark, which switches on the dark values of the
  // palette roles in custom.css (only when data-bs-theme="dark"). Modules not
  // listed keep their current dark mode. Add a module when its dark pass is done.
  const AFIMS_DARK_MODULES = ["UAC", "ACCOUNTING", "ACCOUNTING-BAPD", "GSD", "ACCOUNTING-DCD", "ACCOUNTING-GAD", "FMD", "TREASURY", "REGIONAL"];
  (function markDarkReady() {
    const module = (location.pathname.split("/")[1] || "").toUpperCase();
    // The FIMS app itself is served from the site root (no module folder in the path), so the
    // first path segment is a page name, not a module: it always gets the AFIMS dark palette.
    const isModuleFolder = /^(UAC|DOCUMENT|ACCOUNTING|ACCOUNTING-BAPD|GSD|ACCOUNTING-DCD|ACCOUNTING-GAD|FMD|TREASURY|REGIONAL|DISTRICT)$/.test(module);
    if (!isModuleFolder || AFIMS_DARK_MODULES.includes(module)) document.documentElement.classList.add("afims-dark");
  })();

  // Stylesheet URL with the file's modified time as ?v= (via _shared/afims-asset.php),
  // so browsers get a changed file right away but keep caching an unchanged one.
  // The web server caches .css for a year, so a fixed ?v= would never refresh.
  const afimsAsset = (file) => "/_shared/afims-asset.php?f=" + file;

  function loadAfimsStyles() {
    const folder = location.pathname.split("/")[1] || "";
    const module = folder.toUpperCase();

    // UAC and DOCUMENT: PHPMaker adds custom.css with a fixed ?v=24.16.8 - point it
    // at the versioned URL so changes reach users without a hard refresh.
    const sharedCss = document.querySelector('link[href*="/_shared/css/custom.css"]');
    if (sharedCss) sharedCss.href = afimsAsset("_shared/css/custom.css");

    if (!AFIMS_UI_MODULES.includes(module)) return;

    if (!sharedCss) {
      const link = document.createElement("link");
      link.rel = "stylesheet";
      link.href = afimsAsset("_shared/css/custom.css");
      // After Bootstrap/AdminLTE (so the palette overrides win), before the module's
      // own stylesheet (so module styles can still override).
      const moduleCss = document.querySelector('link[href*="/_shared/css/fmd.css"], link[href*="/fims-treasury.css"]');
      if (moduleCss) moduleCss.before(link);
      else document.head.appendChild(link);
    }

    // The module's own UI overrides (owned by that module's team), loaded last so
    // they restyle the module's older components: /<MODULE>/app/css/afims-ui.css
    const moduleUi = document.createElement("link");
    moduleUi.rel = "stylesheet";
    moduleUi.href = afimsAsset(module + "/app/css/afims-ui.css");
    document.head.appendChild(moduleUi);
  }

  // ── Chart.js theme for modules in AFIMS_UI_MODULES ────────────────────────
  // Restyles every chart on the module's pages to the UAC dashboard look without
  // editing each chart: colors are mapped to the validated chart palette
  // (--afims-chart-*) by hue family so meaning is kept (green stays green, red
  // stays red), lines get 2px + fading fill, bars rounded ends, pies become
  // donuts, and grid/ticks/legend/tooltip use the palette. A chart can opt out
  // with options.plugins.afimsTheme = false.
  function installChartTheme() {
    const module = (location.pathname.split("/")[1] || "").toUpperCase();
    if (!AFIMS_UI_MODULES.includes(module)) return;

    const cssVar = (name, fallback) =>
      getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
    const pal = () => ({
      blue: cssVar("--afims-chart-1", "#2A78D6"),
      orange: cssVar("--afims-chart-2", "#EB6834"),
      green: cssVar("--afims-chart-3", "#1BAF7A"),
      yellow: cssVar("--afims-chart-4", "#EDA100"),
      pink: cssVar("--afims-chart-5", "#E87BA4"),
      violet: cssVar("--afims-chart-6", "#4A3AA7"),
      red: cssVar("--afims-danger", "#DC3545"),
      other: cssVar("--afims-chart-other", "#C3C5D1"),
      grid: cssVar("--afims-chart-grid", "#EDF0F4"),
      axis: cssVar("--afims-chart-axis", "#62657E"),
      text: cssVar("--afims-text", "#1A1B21"),
      surface: cssVar("--afims-surface", "#FFFFFF"),
    });
    const ORDER = ["blue", "orange", "green", "yellow", "pink", "violet"];

    const parse = c => {
      if (typeof c !== "string") return null;
      let m = c.trim().match(/^#([0-9a-f]{3}|[0-9a-f]{6})$/i);
      if (m) {
        let h = m[1];
        if (h.length === 3) h = h.split("").map(x => x + x).join("");
        const n = parseInt(h, 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255, 1];
      }
      m = c.match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+%?))?\s*\)/i);
      if (m) {
        let a = m[4] === undefined ? 1 : m[4].endsWith("%") ? parseFloat(m[4]) / 100 : +m[4];
        return [+m[1], +m[2], +m[3], a];
      }
      return null;
    };
    const family = ([r, g, b]) => {
      r /= 255; g /= 255; b /= 255;
      const max = Math.max(r, g, b), min = Math.min(r, g, b), l = (max + min) / 2, d = max - min;
      const s = d === 0 ? 0 : d / (1 - Math.abs(2 * l - 1));
      // greys incl. Tailwind slate greys (#94a3b8, #cbd5e1 have ~20-30% saturation)
      if (s < 0.15 || l > 0.95 || (s < 0.32 && l > 0.55)) return "other";
      let h = 0;
      if (max === r) h = ((g - b) / d) % 6; else if (max === g) h = (b - r) / d + 2; else h = (r - g) / d + 4;
      h = (h * 60 + 360) % 360;
      if (h < 15 || h >= 345) return "red";
      if (h < 34) return "orange";   // amber (#f59e0b ~38deg) counts as yellow
      if (h < 70) return "yellow";
      if (h < 170) return "green";
      if (h < 250) return "blue";
      if (h < 300) return "violet";
      return "pink";
    };
    const withAlpha = (hex, a) => {
      if (a >= 1) return hex;
      const [r, g, b] = parse(hex) || [0, 0, 0];
      return `rgba(${r}, ${g}, ${b}, ${a})`;
    };

    function themeChart(chart) {
      // raw user options (chart.options is a resolver proxy - writing to it breaks Chart.js)
      const opts = chart.config.options || (chart.config.options = {});
      if (chart.$afimsThemed || (opts.plugins && opts.plugins.afimsTheme === false)) return;
      chart.$afimsThemed = true;
      const p = pal();
      const type = chart.config.type;
      const font = { family: getComputedStyle(document.body).fontFamily, size: 12 };

      // Map each distinct original color to a palette color of the same family;
      // if that palette color is taken in this chart, use the next free slot.
      const assigned = new Map(), used = new Set();
      const mapColor = c => {
        if (typeof c !== "string") return c;
        const rgba = parse(c);
        if (!rgba) return c;
        const key = rgba.slice(0, 3).join(",");
        let name = assigned.get(key);
        if (!name) {
          name = family(rgba);
          if (name !== "other" && name !== "red" && used.has(name)) {
            name = ORDER.find(n => !used.has(n)) || name;
          }
          assigned.set(key, name);
          used.add(name);
        }
        return withAlpha(p[name], rgba[3]);
      };
      const mapProp = v => (Array.isArray(v) ? v.map(mapColor) : typeof v === "string" ? mapColor(v) : v);
      // One bar/line series colored in shades of one hue = one series -> one color
      const singleHue = arr => {
        const fams = arr.map(c => { const x = parse(c); return x ? family(x) : null; }).filter(Boolean);
        return fams.length > 1 && fams.every(fm => fm === fams[0]) && fams[0] !== "other" ? fams[0] : null;
      };

      (chart.config.data.datasets || []).forEach((ds, i) => {
        const dsType = ds.type || type;
        if ((dsType === "bar" || dsType === "line") && Array.isArray(ds.backgroundColor)) {
          const fam = singleHue(ds.backgroundColor);
          if (fam) ds.backgroundColor = ds.backgroundColor.find(c => parse(c)); // one color, mapped below
        }
        ["backgroundColor", "borderColor", "pointBackgroundColor", "hoverBackgroundColor", "hoverBorderColor"]
          .forEach(k => { if (ds[k] !== undefined) ds[k] = mapProp(ds[k]); });

        if (dsType === "line") {
          const line = typeof ds.borderColor === "string" ? ds.borderColor : p[ORDER[i % ORDER.length]];
          ds.borderColor = line;
          ds.borderWidth = 2;
          ds.tension = ds.tension ?? 0.35;
          ds.pointRadius = 0;
          ds.pointHoverRadius = 5;
          ds.pointHoverBorderWidth = 2;
          ds.pointHoverBorderColor = p.surface;
          ds.pointHoverBackgroundColor = line;
          if (ds.fill) {
            ds.backgroundColor = ctx => {
              const area = ctx.chart.chartArea;
              if (!area) return withAlpha(line, 0.12);
              const g = ctx.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
              g.addColorStop(0, withAlpha(line, 0.2));
              g.addColorStop(1, withAlpha(line, 0));
              return g;
            };
          }
        } else if (dsType === "bar") {
          ds.borderWidth = 0;
          ds.borderRadius = 4;
          ds.borderSkipped = false;
          ds.maxBarThickness = ds.maxBarThickness ?? 36;
          if (ds.backgroundColor === undefined) ds.backgroundColor = p[ORDER[i % ORDER.length]];
        } else if (dsType === "pie" || dsType === "doughnut") {
          ds.borderWidth = 2;
          ds.borderColor = p.surface;
          ds.hoverOffset = 6;
        }
      });

      if (type === "pie" || type === "doughnut") opts.cutout = "68%";

      if ((type === "line" || type === "bar") && !opts.scales) opts.scales = { x: {}, y: {} };
      if (opts.scales) {
        Object.values(opts.scales).forEach(sc => {
          sc.grid = Object.assign({}, sc.grid, { color: p.grid, drawTicks: false });
          sc.border = Object.assign({}, sc.border, { display: false });
          sc.ticks = Object.assign({}, sc.ticks, { color: p.axis, font, padding: 6 });
        });
        if (opts.scales.x && (type === "line" || type === "bar")) {
          const horizontal = opts.indexAxis === "y";
          if (!horizontal) opts.scales.x.grid.display = false;
        }
      }

      opts.plugins = opts.plugins || {};
      if (opts.plugins.legend !== false) {
        const lg = opts.plugins.legend || (opts.plugins.legend = {});
        lg.labels = Object.assign({}, lg.labels, { color: p.text, font, usePointStyle: true, boxWidth: 8, boxHeight: 8, padding: 14 });
      }
      opts.plugins.tooltip = Object.assign({}, opts.plugins.tooltip, {
        backgroundColor: p.surface, titleColor: p.text, bodyColor: p.text,
        borderColor: p.grid, borderWidth: 1, padding: 10, usePointStyle: true, boxPadding: 4,
        titleFont: Object.assign({}, font, { weight: "600" }), bodyFont: font,
      });
      if (type === "line") opts.interaction = Object.assign({ mode: "index", intersect: false }, opts.interaction);

      chart.update("none");
    }

    function register() {
      if (window.Chart.$afimsTheme) return;
      window.Chart.$afimsTheme = true;
      window.Chart.register({ id: "afimsTheme", afterInit: chart => themeChart(chart) });
      // charts created before this ran
      Object.values(window.Chart.instances || {}).forEach(themeChart);
    }

    let tries = 0;
    (function waitForChart() {
      if (window.Chart && typeof window.Chart.register === "function") register();
      else if (tries++ < 150) setTimeout(waitForChart, 200); // up to 30s
    })();
  }

  // TREASURY "Actions" menus (.fims-dd) open with position:absolute inside the
  // scrolling .fims-table-wrap, so the table clipped them (menus on the last rows
  // were cut off). Pin the open menu to the viewport under its trigger (above it
  // when there is no room below); scrolling closes it, resizing moves it with its row.
  function floatTableDropdowns() {
    const place = () => {
      document.querySelectorAll(".fims-dd.open > .fims-dd-menu").forEach((menu) => {
        const trigger = menu.parentElement.querySelector(".fims-dd-trigger") || menu.parentElement;
        const r = trigger.getBoundingClientRect();
        Object.assign(menu.style, { position: "fixed", right: "auto", left: "0px", top: "0px" });
        const w = menu.offsetWidth, h = menu.offsetHeight;
        const fitsBelow = r.bottom + 3 + h <= window.innerHeight - 8;
        menu.style.top = (fitsBelow ? r.bottom + 3 : Math.max(8, r.top - 3 - h)) + "px";
        menu.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + "px";
      });
    };
    // Page handlers toggle .open in the trigger's onclick and stop propagation,
    // so listen in the capture phase and place the menu after they ran.
    document.addEventListener("click", (e) => {
      if (e.target.closest && e.target.closest(".fims-dd-trigger")) setTimeout(place, 0);
    }, true);
    const closeAll = (e) => {
      if (e && e.target && e.target.closest && e.target.closest(".fims-dd-menu")) return;
      document.querySelectorAll(".fims-dd.open").forEach((dd) => dd.classList.remove("open"));
    };
    window.addEventListener("scroll", closeAll, true);
    window.addEventListener("resize", place);
  }

  // Tag custom pages so the shadcn-style UI in custom.css applies only to them.
  // PHPMaker-generated pages set ew.PAGE_ID ("list", "edit", ...); custom pages don't.
  function markCustomPage() {
    const pageId = window.ew && window.ew.PAGE_ID;
    if (!pageId || pageId === "custom") {
      document.body.classList.add("afims-ui");
    }
  }

  // Initialize everything
  function initialize() {
    loadAfimsStyles();
    installChartTheme();
    floatTableDropdowns();
    injectCSS();

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", markCustomPage);
    } else {
      markCustomPage();
    }

    // Load mini sidebar enhancer as reusable module
    loadjs(["/_shared/js/mini-sidebar.js"], 'mini-sidebar');

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", handleWindowLoad);
    } else {
      handleWindowLoad();
    }
  }

  // Start initialization
  initialize();
})();