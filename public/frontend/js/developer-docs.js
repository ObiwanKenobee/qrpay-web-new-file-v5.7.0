/* Developer documentation: environment switch, language tabs, copy, sidebar search */
(function () {
    "use strict";

    var store = {
        get: function (key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
        set: function (key, value) { try { localStorage.setItem(key, value); } catch (e) {} }
    };

    var shell = document.querySelector(".dx-shell");
    var envs = {};
    try { envs = JSON.parse(shell.getAttribute("data-envs")) || {}; } catch (e) {}

    var currentEnv = envs[store.get("dx-env")] ? store.get("dx-env") : "sandbox";

    function fill(text) {
        var env = envs[currentEnv] || {};
        return text
            .split("__BASE_URL__").join(env.url || "")
            .split("__BASE_PATH__").join(env.path || "")
            .split("__HOST__").join(env.host || "");
    }

    function highlight(el) {
        if (!window.hljs) return;
        el.removeAttribute("data-highlighted");
        el.classList.remove("hljs");
        window.hljs.highlightElement(el);
    }

    function renderCode() {
        document.querySelectorAll("code[data-template]").forEach(function (el) {
            el.textContent = fill(el.getAttribute("data-template"));
            highlight(el);
        });
        document.querySelectorAll("[data-base-url]").forEach(function (el) {
            el.textContent = (envs[currentEnv] || {}).url || "";
        });
        document.querySelectorAll("[data-env-only]").forEach(function (el) {
            el.style.display = el.getAttribute("data-env-only") === currentEnv ? "" : "none";
        });
    }

    function setEnv(env) {
        if (!envs[env]) return;
        currentEnv = env;
        store.set("dx-env", env);
        document.querySelectorAll(".dx-env-switch button, [data-env-pick]").forEach(function (btn) {
            var value = btn.getAttribute("data-env") || btn.getAttribute("data-env-pick");
            btn.classList.toggle("active", value === env);
        });
        renderCode();
    }

    document.querySelectorAll(".dx-env-switch button, [data-env-pick]").forEach(function (btn) {
        btn.addEventListener("click", function () {
            setEnv(btn.getAttribute("data-env") || btn.getAttribute("data-env-pick"));
        });
    });

    // ----- Language tabs (synced across every block on the page) -----
    var blocks = document.querySelectorAll("[data-code-tabs]");

    function setLang(lang, persist) {
        blocks.forEach(function (block) {
            var first = block.querySelector(".dx-pre");
            var use = block.querySelector('.dx-pre[data-lang="' + lang + '"]') ? lang : (first ? first.getAttribute("data-lang") : null);
            block.querySelectorAll(".dx-code-tabs button").forEach(function (b) {
                b.classList.toggle("active", b.getAttribute("data-lang") === use);
            });
            block.querySelectorAll(".dx-pre").forEach(function (p) {
                p.classList.toggle("active", p.getAttribute("data-lang") === use);
            });
            var select = block.querySelector(".dx-code-select");
            if (select) select.value = use;
        });
        if (persist) store.set("dx-lang", lang);
    }

    blocks.forEach(function (block) {
        block.querySelectorAll(".dx-code-tabs button").forEach(function (b) {
            b.addEventListener("click", function () { setLang(b.getAttribute("data-lang"), true); });
        });
        var select = block.querySelector(".dx-code-select");
        if (select) select.addEventListener("change", function () { setLang(select.value, true); });
    });

    // ----- Response status tabs -----
    document.querySelectorAll("[data-response-tabs]").forEach(function (block) {
        block.querySelectorAll(".dx-status").forEach(function (btn) {
            btn.addEventListener("click", function () {
                var id = btn.getAttribute("data-status");
                block.querySelectorAll(".dx-status").forEach(function (b) { b.classList.toggle("active", b === btn); });
                block.querySelectorAll(".dx-response").forEach(function (r) {
                    r.classList.toggle("active", r.getAttribute("data-status") === id);
                });
            });
        });
    });

    // ----- Copy -----
    function copy(text, btn) {
        var done = function () {
            var icon = btn.querySelector("i");
            btn.classList.add("copied");
            if (icon) icon.className = "las la-check";
            setTimeout(function () {
                btn.classList.remove("copied");
                if (icon) icon.className = "las la-copy";
            }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done);
            return;
        }
        var area = document.createElement("textarea");
        area.value = text;
        area.style.position = "fixed";
        area.style.opacity = "0";
        document.body.appendChild(area);
        area.select();
        try { document.execCommand("copy"); done(); } catch (e) {}
        document.body.removeChild(area);
    }

    document.addEventListener("click", function (e) {
        var btn = e.target.closest(".dx-copy");
        if (!btn) return;
        if (btn.hasAttribute("data-copy-endpoint")) {
            copy(((envs[currentEnv] || {}).url || "") + btn.getAttribute("data-copy-endpoint"), btn);
        } else if (btn.hasAttribute("data-copy-text")) {
            copy(fill(btn.getAttribute("data-copy-text")), btn);
        } else {
            var block = btn.closest(".dx-code");
            var code = block && (block.querySelector(".dx-pre.active code") || block.querySelector(".dx-response.active code"));
            if (code) copy(code.textContent, btn);
        }
    });

    // ----- Sidebar (mobile) -----
    var sidebar = document.querySelector(".dx-sidebar");
    var overlay = document.getElementById("body-overlay");
    var menuBtn = document.querySelector(".dx-menu-btn");
    if (menuBtn && sidebar) {
        menuBtn.addEventListener("click", function () {
            sidebar.classList.toggle("active");
            if (overlay) overlay.classList.toggle("active", sidebar.classList.contains("active"));
        });
    }
    if (overlay && sidebar) {
        overlay.addEventListener("click", function () {
            sidebar.classList.remove("active");
            overlay.classList.remove("active");
        });
    }

    // ----- Sidebar search -----
    var search = document.querySelector(".dx-search-input");
    if (search) {
        search.addEventListener("input", function () {
            var q = search.value.trim().toLowerCase();
            var any = false;
            document.querySelectorAll(".dx-nav-group").forEach(function (group) {
                var visible = 0;
                group.querySelectorAll("li").forEach(function (li) {
                    var show = !q || li.textContent.toLowerCase().indexOf(q) !== -1;
                    li.style.display = show ? "" : "none";
                    if (show) visible++;
                });
                group.style.display = visible ? "" : "none";
                if (visible) any = true;
            });
            var empty = document.querySelector(".dx-nav-empty");
            if (empty) empty.style.display = any ? "none" : "block";
        });
        search.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
                var first = Array.prototype.find.call(document.querySelectorAll(".dx-nav li"), function (li) {
                    return li.style.display !== "none";
                });
                if (first) window.location = first.querySelector("a").href;
            }
            if (e.key === "Escape") search.blur();
        });
        document.addEventListener("keydown", function (e) {
            var tag = (document.activeElement || {}).tagName;
            if (e.key === "/" && tag !== "INPUT" && tag !== "TEXTAREA") {
                e.preventDefault();
                search.focus();
            }
        });
    }

    // ----- Init -----
    var savedLang = store.get("dx-lang");
    setLang(savedLang || (blocks[0] && blocks[0].querySelector(".dx-pre") ? blocks[0].querySelector(".dx-pre").getAttribute("data-lang") : "curl"), false);
    setEnv(currentEnv);
    document.querySelectorAll(".dx-response code").forEach(highlight);
})();
