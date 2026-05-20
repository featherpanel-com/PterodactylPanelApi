import { createApp } from "vue";
import "./style.css";
import App from "./App.vue";
import router from "./router";
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";

const app = createApp(App);

app.use(router);
app.use(Toast, {
  transition: "Vue-Toastification__bounce",
  maxToasts: 20,
  newestOnTop: true,
});

function applyTheme(theme: "light" | "dark") {
  if (theme === "dark") {
    document.documentElement.classList.add("dark");
  } else {
    document.documentElement.classList.remove("dark");
  }
}

window.addEventListener("message", (event) => {
  if (event.data?.type === "featherpanel-theme") {
    applyTheme(event.data.theme);
  }
});

if (window.parent !== window) {
  window.parent.postMessage({ type: "featherpanel-ready" }, "*");
}

applyTheme("dark");

router.isReady().then(() => {
  app.mount("#app");
});
