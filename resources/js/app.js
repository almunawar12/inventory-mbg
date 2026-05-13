import flatpickr from "flatpickr";
import TomSelect from "tom-select";
import Alpine from "alpinejs";
import "./bootstrap";
import "./../../vendor/power-components/livewire-powergrid/dist/powergrid";

// @ts-ignore
window.TomSelect = TomSelect;
window.flatpickr = flatpickr;

// Set Alpine globally before Livewire initializes — Livewire v3 calls Alpine.start()
window.Alpine = Alpine;
