from __future__ import annotations

import logging
import os
import tkinter as tk
from pathlib import Path
from tkinter import filedialog, font, messagebox, scrolledtext, ttk
import webbrowser

from .core import (
    DEFAULT_IN_STOCK_AVAILABILITY,
    DEFAULT_OUT_OF_STOCK_AVAILABILITY,
    FeedSettings,
    ProductOverride,
    ResolvedProduct,
    auto_detect_link_template,
    count_statuses,
    export_xml,
    load_project,
    load_woocommerce_csv,
    resolve_products,
    save_project,
)
from .logging_utils import DEFAULT_ACTIVITY_LOG_LEVEL, configure_logging


DOCS_URL = "https://developer.skroutz.gr/products/xml_feed/"
VALIDATOR_URL = "https://validator.skroutz.gr/"


class TkTextLogHandler(logging.Handler):
    def __init__(self, widget: scrolledtext.ScrolledText) -> None:
        super().__init__()
        self.widget = widget

    def emit(self, record: logging.LogRecord) -> None:
        message = self.format(record)

        def append() -> None:
            try:
                self.widget.configure(state="normal")
                self.widget.insert("end", message + "\n")
                self.widget.see("end")
                self.widget.configure(state="disabled")
            except tk.TclError:
                pass

        try:
            self.widget.after(0, append)
        except tk.TclError:
            pass


class FeedBuilderApp(tk.Tk):
    def __init__(self) -> None:
        super().__init__()
        self.logger, self.log_path = configure_logging()
        self.logger.debug("Initializing FeedBuilderApp window.")
        self.title("Skroutz Feed Builder")
        self.geometry("1480x920")
        self.minsize(1180, 760)
        self.configure(bg="#f4efe5")
        self.protocol("WM_DELETE_WINDOW", self.on_close)

        self.csv_path: Path | None = None
        self.project_path: Path | None = None
        self.source_products = []
        self.resolved_products: list[ResolvedProduct] = []
        self.overrides: dict[str, ProductOverride] = {}
        self.settings = FeedSettings()
        self.selected_product_id: str | None = None
        self.populating_editor = False
        self.suppress_tree_select = False
        self.editor_dirty = False
        self.log_handler: TkTextLogHandler | None = None

        self.status_var = tk.StringVar(value="Load a WooCommerce CSV to start building a Skroutz feed.")
        self.summary_vars = {key: tk.StringVar(value="0") for key in ("total", "included", "ready", "review", "needs_fixes", "excluded")}

        self.root_element_var = tk.StringVar(value=self.settings.root_element)
        self.link_template_var = tk.StringVar(value=self.settings.link_template)
        self.default_manufacturer_var = tk.StringVar(value=self.settings.default_manufacturer)
        self.vat_rate_var = tk.StringVar(value=self.settings.vat_rate)
        self.in_stock_availability_var = tk.StringVar(value=self.settings.in_stock_availability)
        self.out_of_stock_availability_var = tk.StringVar(value=self.settings.out_of_stock_availability)
        self.export_only_published_var = tk.BooleanVar(value=self.settings.export_only_published)
        self.skip_hidden_catalog_var = tk.BooleanVar(value=self.settings.skip_hidden_catalog)

        self.include_var = tk.BooleanVar(value=True)
        self.editor_vars = {name: tk.StringVar() for name in (
            "name",
            "link",
            "image",
            "additional_images",
            "category",
            "price",
            "vat",
            "manufacturer",
            "mpn",
            "ean",
            "availability",
            "weight",
            "color",
            "size",
            "quantity",
        )}

        self._configure_style()
        self._build_ui()
        self._attach_dirty_tracking()
        self._attach_log_handler()
        self.logger.info("Application started. Log file: %s", self.log_path)
        self._set_status("Load a WooCommerce CSV to start building a Skroutz feed.")

    def _configure_style(self) -> None:
        self.logger.debug("Configuring Tkinter styles.")
        self.option_add("*Font", "{Segoe UI} 10")
        title_font = font.Font(family="Bahnschrift SemiBold", size=12)
        style = ttk.Style(self)
        style.theme_use("clam")
        style.configure(".", background="#f4efe5", foreground="#24303f")
        style.configure("TFrame", background="#f4efe5")
        style.configure("TLabelframe", background="#f4efe5", bordercolor="#d9cda6", relief="solid")
        style.configure("TLabelframe.Label", background="#f4efe5", foreground="#16324f", font=title_font)
        style.configure("TLabel", background="#f4efe5")
        style.configure("Subtle.TLabel", foreground="#6b7280")
        style.configure("Accent.TButton", background="#1f6f78", foreground="#ffffff", borderwidth=0, padding=(12, 8))
        style.map("Accent.TButton", background=[("active", "#175860")])
        style.configure("Secondary.TButton", background="#dccda6", foreground="#24303f", borderwidth=0, padding=(12, 8))
        style.map("Secondary.TButton", background=[("active", "#cbb987")])
        style.configure("Treeview", background="#fffdf8", fieldbackground="#fffdf8", rowheight=28)
        style.configure("Treeview.Heading", background="#eadfc2", foreground="#16324f", font=title_font)

    def _build_ui(self) -> None:
        self.logger.debug("Building main UI layout.")
        outer = ttk.Frame(self, padding=16)
        outer.pack(fill="both", expand=True)

        toolbar = ttk.Frame(outer)
        toolbar.pack(fill="x")
        ttk.Button(toolbar, text="Open CSV", style="Accent.TButton", command=self.open_csv).pack(side="left")
        ttk.Button(toolbar, text="Load Project", style="Secondary.TButton", command=self.load_saved_project).pack(side="left", padx=(10, 0))
        ttk.Button(toolbar, text="Save Project", style="Secondary.TButton", command=self.save_current_project).pack(side="left", padx=(10, 0))
        ttk.Button(toolbar, text="Generate XML", style="Accent.TButton", command=self.generate_xml).pack(side="left", padx=(18, 0))
        ttk.Button(toolbar, text="Open Log", command=self.open_log_file).pack(side="right")
        ttk.Button(toolbar, text="Validator", command=lambda: webbrowser.open(VALIDATOR_URL)).pack(side="right")
        ttk.Button(toolbar, text="Docs", command=lambda: webbrowser.open(DOCS_URL)).pack(side="right", padx=(0, 10))

        ttk.Label(outer, textvariable=self.status_var, style="Subtle.TLabel").pack(fill="x", pady=(12, 14))

        cards = tk.Frame(outer, bg="#f4efe5")
        cards.pack(fill="x", pady=(0, 14))
        self._summary_card(cards, "Rows", self.summary_vars["total"], "#16324f", 0)
        self._summary_card(cards, "Included", self.summary_vars["included"], "#1f6f78", 1)
        self._summary_card(cards, "Ready", self.summary_vars["ready"], "#2f855a", 2)
        self._summary_card(cards, "Review", self.summary_vars["review"], "#946200", 3)
        self._summary_card(cards, "Need Fixes", self.summary_vars["needs_fixes"], "#b45309", 4)
        self._summary_card(cards, "Excluded", self.summary_vars["excluded"], "#6b7280", 5)

        split = ttk.Panedwindow(outer, orient="horizontal")
        split.pack(fill="both", expand=True)
        left = ttk.Frame(split, padding=(0, 0, 10, 0))
        right = ttk.Frame(split)
        split.add(left, weight=3)
        split.add(right, weight=2)

        self._build_product_list(left)
        self._build_settings_panel(right)
        self._build_editor(right)

    def _summary_card(self, parent: tk.Widget, title: str, value: tk.StringVar, color: str, column: int) -> None:
        frame = tk.Frame(parent, bg="#fffdf8", highlightbackground="#d8cfb2", highlightthickness=1, padx=14, pady=12)
        frame.grid(row=0, column=column, padx=(0, 10), sticky="nsew")
        tk.Label(frame, text=title, bg="#fffdf8", fg=color, font=("Bahnschrift SemiBold", 11)).pack(anchor="w")
        tk.Label(frame, textvariable=value, bg="#fffdf8", fg="#24303f", font=("Bahnschrift", 22)).pack(anchor="w", pady=(8, 0))
        parent.grid_columnconfigure(column, weight=1)

    def _build_product_list(self, parent: ttk.Frame) -> None:
        self.logger.debug("Building product list and activity log panels.")
        frame = ttk.LabelFrame(parent, text="Products", padding=10)
        frame.pack(fill="both", expand=True)

        columns = ("status", "id", "name", "price", "ean")
        self.tree = ttk.Treeview(frame, columns=columns, show="headings", selectmode="browse")
        for key, text, width, anchor in (
            ("status", "Status", 110, "center"),
            ("id", "ID", 90, "center"),
            ("name", "Name", 520, "w"),
            ("price", "Price", 100, "e"),
            ("ean", "EAN", 150, "center"),
        ):
            self.tree.heading(key, text=text)
            self.tree.column(key, width=width, anchor=anchor)
        self.tree.tag_configure("ready", background="#eef9f1")
        self.tree.tag_configure("review", background="#fff6dd")
        self.tree.tag_configure("error", background="#fdecec")
        self.tree.tag_configure("excluded", background="#eef1f3", foreground="#6b7280")
        self.tree.bind("<<TreeviewSelect>>", self.on_tree_select)

        scroll = ttk.Scrollbar(frame, orient="vertical", command=self.tree.yview)
        self.tree.configure(yscrollcommand=scroll.set)
        self.tree.pack(side="left", fill="both", expand=True)
        scroll.pack(side="right", fill="y")

        log_frame = ttk.LabelFrame(parent, text="Activity Log", padding=10)
        log_frame.pack(fill="both", expand=False, pady=(14, 0))
        meta = ttk.Frame(log_frame)
        meta.pack(fill="x")
        ttk.Label(meta, text=f"Log file: {self.log_path}", style="Subtle.TLabel").pack(side="left")
        ttk.Button(meta, text="Open Log File", command=self.open_log_file).pack(side="right")

        self.activity_log = scrolledtext.ScrolledText(log_frame, height=10, wrap="word", font=("Consolas", 9), bg="#fffdf8", relief="solid", borderwidth=1)
        self.activity_log.pack(fill="both", expand=True, pady=(8, 0))
        self.activity_log.configure(state="disabled")

    def _build_settings_panel(self, parent: ttk.Frame) -> None:
        self.logger.debug("Building feed settings panel.")
        frame = ttk.LabelFrame(parent, text="Feed Settings", padding=10)
        frame.pack(fill="x")
        frame.columnconfigure(1, weight=1)

        self._form_row(frame, "Root element", self.root_element_var, 0)
        self._form_row(frame, "Link template", self.link_template_var, 1, "Use {slug}, {id}, {sku}, {sku_or_id}, {parent_id}")
        self._form_row(frame, "Default manufacturer", self.default_manufacturer_var, 2)
        self._form_row(frame, "VAT rate", self.vat_rate_var, 3)
        self._form_row(frame, "In-stock availability", self.in_stock_availability_var, 4)
        self._form_row(frame, "Out-of-stock availability", self.out_of_stock_availability_var, 5)
        ttk.Checkbutton(frame, text="Export only published products", variable=self.export_only_published_var).grid(row=6, column=0, columnspan=2, sticky="w", pady=(10, 0))
        ttk.Checkbutton(frame, text="Skip hidden/search-only catalog items", variable=self.skip_hidden_catalog_var).grid(row=7, column=0, columnspan=2, sticky="w", pady=(6, 0))
        ttk.Button(frame, text="Apply Settings", style="Accent.TButton", command=self.apply_settings).grid(row=8, column=0, columnspan=2, sticky="ew", pady=(14, 0))

    def _build_editor(self, parent: ttk.Frame) -> None:
        self.logger.debug("Building product editor panel.")
        frame = ttk.LabelFrame(parent, text="Product Editor", padding=10)
        frame.pack(fill="both", expand=True, pady=(14, 0))
        frame.columnconfigure(1, weight=1)

        ttk.Checkbutton(frame, text="Include this product in export", variable=self.include_var).grid(row=0, column=0, columnspan=2, sticky="w", pady=(0, 10))
        row = 1
        for label, key, help_text in (
            ("Name", "name", None),
            ("Link", "link", None),
            ("Main image", "image", None),
            ("Additional images", "additional_images", "Comma-separated URLs"),
            ("Category", "category", None),
            ("Price", "price", None),
            ("VAT", "vat", None),
            ("Manufacturer", "manufacturer", None),
            ("MPN", "mpn", None),
            ("EAN", "ean", None),
            ("Availability", "availability", None),
            ("Weight (grams)", "weight", None),
            ("Color", "color", None),
            ("Size", "size", None),
            ("Quantity", "quantity", None),
        ):
            self._form_row(frame, label, self.editor_vars[key], row, help_text)
            row += 1

        ttk.Label(frame, text="Description").grid(row=row, column=0, sticky="nw", pady=(10, 0), padx=(0, 10))
        self.description_text = scrolledtext.ScrolledText(frame, height=10, wrap="word", font=("Segoe UI", 10), bg="#fffdf8", relief="solid", borderwidth=1)
        self.description_text.grid(row=row, column=1, sticky="nsew", pady=(10, 0))
        row += 1

        ttk.Label(frame, text="Validation").grid(row=row, column=0, sticky="nw", pady=(10, 0), padx=(0, 10))
        self.validation_text = scrolledtext.ScrolledText(frame, height=8, wrap="word", font=("Consolas", 9), bg="#fffdf8", relief="solid", borderwidth=1)
        self.validation_text.grid(row=row, column=1, sticky="nsew", pady=(10, 0))
        self.validation_text.configure(state="disabled")
        row += 1

        actions = ttk.Frame(frame)
        actions.grid(row=row, column=0, columnspan=2, sticky="ew", pady=(12, 0))
        ttk.Button(actions, text="Apply Product Changes", style="Accent.TButton", command=self.apply_product_changes).pack(side="left")
        ttk.Button(actions, text="Reset Overrides", style="Secondary.TButton", command=self.reset_product_changes).pack(side="left", padx=(10, 0))

        frame.rowconfigure(row - 2, weight=2)
        frame.rowconfigure(row - 1, weight=1)

    def _form_row(self, parent: ttk.Frame, label: str, variable: tk.StringVar, row: int, help_text: str | None = None) -> None:
        ttk.Label(parent, text=label).grid(row=row, column=0, sticky="nw", pady=(6, 0), padx=(0, 10))
        container = ttk.Frame(parent)
        container.grid(row=row, column=1, sticky="ew", pady=(6, 0))
        ttk.Entry(container, textvariable=variable).pack(fill="x")
        if help_text:
            ttk.Label(container, text=help_text, style="Subtle.TLabel").pack(anchor="w", pady=(2, 0))

    def _current_settings(self) -> FeedSettings:
        return FeedSettings(
            root_element=self.root_element_var.get().strip() or "mywebstore",
            link_template=self.link_template_var.get().strip(),
            default_manufacturer=self.default_manufacturer_var.get().strip(),
            vat_rate=self.vat_rate_var.get().strip() or "24.00",
            in_stock_availability=self.in_stock_availability_var.get().strip() or DEFAULT_IN_STOCK_AVAILABILITY,
            out_of_stock_availability=self.out_of_stock_availability_var.get().strip() or DEFAULT_OUT_OF_STOCK_AVAILABILITY,
            export_only_published=self.export_only_published_var.get(),
            skip_hidden_catalog=self.skip_hidden_catalog_var.get(),
        )

    def _attach_dirty_tracking(self) -> None:
        for variable in self.editor_vars.values():
            variable.trace_add("write", self._on_editor_field_changed)
        self.include_var.trace_add("write", self._on_editor_field_changed)
        self.description_text.bind("<<Modified>>", self._on_description_modified)
        self.logger.debug("Attached editor dirty-state tracking.")

    def _on_editor_field_changed(self, *_args: object) -> None:
        if self.populating_editor:
            return
        if not self.editor_dirty:
            self.editor_dirty = True
            self.logger.debug("Editor state marked dirty by field change.")

    def _on_description_modified(self, _event: object) -> None:
        if self.populating_editor:
            self.description_text.edit_modified(False)
            return
        if self.description_text.edit_modified():
            self.description_text.edit_modified(False)
            if not self.editor_dirty:
                self.editor_dirty = True
                self.logger.debug("Editor state marked dirty by description change.")

    def _attach_log_handler(self) -> None:
        if self.log_handler is not None:
            self.logger.removeHandler(self.log_handler)
        self.log_handler = TkTextLogHandler(self.activity_log)
        self.log_handler.setLevel(DEFAULT_ACTIVITY_LOG_LEVEL)
        self.log_handler.setFormatter(logging.Formatter("%(asctime)s %(levelname)-7s %(message)s", "%H:%M:%S"))
        self.logger.addHandler(self.log_handler)
        self.logger.debug("Attached Tk activity log handler.")

    def _set_status(self, message: str, level: int = logging.INFO) -> None:
        self.status_var.set(message)
        self.logger.log(level, message)

    def _log_settings_snapshot(self, context: str) -> None:
        settings = self._current_settings()
        self.logger.debug(
            "%s | settings root=%s link_template=%s default_manufacturer=%s vat=%s in_stock=%s out_of_stock=%s export_only_published=%s skip_hidden_catalog=%s",
            context,
            settings.root_element,
            settings.link_template,
            settings.default_manufacturer,
            settings.vat_rate,
            settings.in_stock_availability,
            settings.out_of_stock_availability,
            settings.export_only_published,
            settings.skip_hidden_catalog,
        )

    def _log_validation_snapshot(self, context: str, max_products: int = 10) -> None:
        summary = count_statuses(self.resolved_products)
        self.logger.info(
            "%s | total=%d included=%d ready=%d review=%d needs_fixes=%d excluded=%d",
            context,
            summary["total"],
            summary["included"],
            summary["ready"],
            summary["review"],
            summary["needs_fixes"],
            summary["excluded"],
        )
        invalid_products = [product for product in self.resolved_products if product.included and product.error_count > 0]
        for product in invalid_products[:max_products]:
            issues = "; ".join(
                f"{issue.field}: {issue.message}" for issue in product.issues if issue.severity == "error"
            )
            self.logger.warning("Blocking issues for product %s (%s): %s", product.source_id, product.name, issues)
        remaining = len(invalid_products) - max_products
        if remaining > 0:
            self.logger.warning("%d more invalid product(s) omitted from this snapshot.", remaining)

    def _log_product_issues(self, product: ResolvedProduct, context: str) -> None:
        self.logger.info(
            "%s | product=%s name=%s status=%s errors=%d warnings=%d",
            context,
            product.source_id,
            product.name,
            product.status,
            product.error_count,
            product.warning_count,
        )
        for issue in product.issues:
            level = logging.ERROR if issue.severity == "error" else logging.WARNING
            self.logger.log(level, "Product %s issue [%s] %s: %s", product.source_id, issue.severity, issue.field, issue.message)

    def _log_editor_snapshot(self, product_id: str) -> None:
        self.logger.debug(
            "Persisting editor state for product %s | included=%s name=%s price=%s ean=%s quantity=%s",
            product_id,
            self.include_var.get(),
            self.editor_vars["name"].get().strip(),
            self.editor_vars["price"].get().strip(),
            self.editor_vars["ean"].get().strip(),
            self.editor_vars["quantity"].get().strip(),
        )

    def open_log_file(self) -> None:
        try:
            if hasattr(os, "startfile"):
                os.startfile(self.log_path)  # type: ignore[attr-defined]
            else:
                webbrowser.open(self.log_path.as_uri())
            self._set_status(f"Opened log file: {self.log_path}")
        except Exception:
            self.logger.exception("Could not open log file %s", self.log_path)
            messagebox.showerror("Could not open log file", f"Log file location:\n{self.log_path}")

    def refresh_products(self, keep_selection: bool = True) -> None:
        if not self.source_products:
            self.logger.debug("refresh_products skipped because no source products are loaded.")
            return
        self.logger.debug("Refreshing products. keep_selection=%s current_selection=%s", keep_selection, self.selected_product_id)
        self.settings = self._current_settings()
        self.resolved_products = resolve_products(self.source_products, self.settings, self.overrides)
        selected = self.selected_product_id if keep_selection else None
        self.tree.delete(*self.tree.get_children())
        for product in self.resolved_products:
            tag = "excluded" if not product.included else "error" if product.error_count else "review" if product.warning_count else "ready"
            self.tree.insert("", "end", iid=product.source_id, values=(product.status, product.source_id, product.name, product.price, product.ean), tags=(tag,))
        summary = count_statuses(self.resolved_products)
        for key, value in summary.items():
            self.summary_vars[key].set(str(value))
        self.logger.debug("Tree refresh complete with %d resolved product rows.", len(self.resolved_products))
        self.suppress_tree_select = True
        if selected and self.tree.exists(selected):
            self.tree.selection_set(selected)
            self.tree.see(selected)
            self.populate_editor(self.find_product(selected))
        elif self.resolved_products:
            first = self.resolved_products[0].source_id
            self.tree.selection_set(first)
            self.tree.see(first)
            self.populate_editor(self.find_product(first))
        self.suppress_tree_select = False

    def open_csv(self) -> None:
        chosen = filedialog.askopenfilename(title="Choose WooCommerce CSV", filetypes=[("CSV files", "*.csv"), ("All files", "*.*")])
        if not chosen:
            self.logger.info("Open CSV dialog was cancelled.")
            return
        try:
            self.csv_path = Path(chosen)
            self.project_path = None
            self.source_products = load_woocommerce_csv(self.csv_path)
            self.overrides = {}
            if not self.link_template_var.get().strip():
                detected = auto_detect_link_template(self.source_products)
                if detected:
                    self.link_template_var.set(detected)
            self._log_settings_snapshot("Before initial refresh after CSV load")
            self.refresh_products(keep_selection=False)
            self._set_status(f"Loaded {len(self.source_products)} rows from {self.csv_path.name}.")
            self._log_validation_snapshot(f"Loaded CSV {self.csv_path}")
        except Exception as exc:
            self.logger.exception("Could not load CSV: %s", chosen)
            messagebox.showerror("Could not load CSV", str(exc))

    def load_saved_project(self) -> None:
        chosen = filedialog.askopenfilename(title="Open project", filetypes=[("JSON files", "*.json"), ("All files", "*.*")])
        if not chosen:
            self.logger.info("Open project dialog was cancelled.")
            return
        try:
            self.project_path = Path(chosen)
            self.csv_path, self.settings, self.overrides = load_project(self.project_path)
            self.source_products = load_woocommerce_csv(self.csv_path)
            self.root_element_var.set(self.settings.root_element)
            self.link_template_var.set(self.settings.link_template)
            self.default_manufacturer_var.set(self.settings.default_manufacturer)
            self.vat_rate_var.set(self.settings.vat_rate)
            self.in_stock_availability_var.set(self.settings.in_stock_availability)
            self.out_of_stock_availability_var.set(self.settings.out_of_stock_availability)
            self.export_only_published_var.set(self.settings.export_only_published)
            self.skip_hidden_catalog_var.set(self.settings.skip_hidden_catalog)
            self._log_settings_snapshot("Before refresh after project load")
            self.refresh_products(keep_selection=False)
            self._set_status(f"Loaded project {self.project_path.name}.")
            self._log_validation_snapshot(f"Loaded project {self.project_path}")
        except Exception as exc:
            self.logger.exception("Could not open project: %s", chosen)
            messagebox.showerror("Could not open project", str(exc))

    def save_current_project(self) -> None:
        if not self.csv_path:
            self.logger.info("Save project requested before a CSV was loaded.")
            messagebox.showinfo("No CSV loaded", "Load a WooCommerce CSV before saving a project.")
            return
        self.persist_editor_state(silent=True)
        target = filedialog.asksaveasfilename(
            title="Save project",
            initialfile=(self.project_path.name if self.project_path else f"{self.csv_path.stem}.skroutz-project.json"),
            initialdir=str(self.project_path.parent if self.project_path else self.csv_path.parent),
            defaultextension=".json",
            filetypes=[("JSON files", "*.json"), ("All files", "*.*")],
        )
        if not target:
            self.logger.info("Save project dialog was cancelled.")
            return
        try:
            self.project_path = save_project(target, self.csv_path, self._current_settings(), self.overrides)
            self._set_status(f"Saved project to {self.project_path.name}.")
        except Exception as exc:
            self.logger.exception("Could not save project to %s", target)
            messagebox.showerror("Could not save project", str(exc))

    def apply_settings(self) -> None:
        if not self.source_products:
            self.logger.info("Apply settings requested before a CSV was loaded.")
            messagebox.showinfo("No CSV loaded", "Load a WooCommerce CSV first.")
            return
        self._log_settings_snapshot("Applying settings")
        self.persist_editor_state(silent=True)
        self.refresh_products()
        self._set_status("Settings applied and products revalidated.")
        self._log_validation_snapshot("Applied settings")

    def on_tree_select(self, _event: object) -> None:
        if self.suppress_tree_select:
            self.logger.debug("Ignoring tree selection event triggered by programmatic selection.")
            return
        selection = self.tree.selection()
        if not selection:
            self.logger.debug("Tree selection event fired with no selection.")
            return
        target_product_id = selection[0]
        if target_product_id == self.selected_product_id and not self.editor_dirty:
            self.logger.debug("Ignoring tree selection event for unchanged product %s.", target_product_id)
            return
        self.persist_editor_state(silent=True)
        self.selected_product_id = target_product_id
        if self.tree.exists(target_product_id):
            self.tree.see(target_product_id)
        self.logger.debug("Tree selection changed to product %s", self.selected_product_id)
        self.populate_editor(self.find_product(self.selected_product_id))

    def find_product(self, product_id: str | None) -> ResolvedProduct | None:
        if not product_id:
            return None
        for product in self.resolved_products:
            if product.source_id == product_id:
                return product
        return None

    def populate_editor(self, product: ResolvedProduct | None) -> None:
        self.populating_editor = True
        try:
            if product is None:
                self.logger.debug("Clearing editor because no product was provided.")
                self.include_var.set(False)
                for var in self.editor_vars.values():
                    var.set("")
                self.description_text.delete("1.0", "end")
                self.set_validation_text("")
                self.editor_dirty = False
                return
            self.logger.debug("Populating editor for product %s (%s)", product.source_id, product.name)
            self.selected_product_id = product.source_id
            self.include_var.set(product.included)
            self.editor_vars["name"].set(product.name)
            self.editor_vars["link"].set(product.link)
            self.editor_vars["image"].set(product.image)
            self.editor_vars["additional_images"].set(", ".join(product.additional_images))
            self.editor_vars["category"].set(product.category)
            self.editor_vars["price"].set(product.price)
            self.editor_vars["vat"].set(product.vat)
            self.editor_vars["manufacturer"].set(product.manufacturer)
            self.editor_vars["mpn"].set(product.mpn)
            self.editor_vars["ean"].set(product.ean)
            self.editor_vars["availability"].set(product.availability)
            self.editor_vars["weight"].set(product.weight)
            self.editor_vars["color"].set(product.color)
            self.editor_vars["size"].set(product.size)
            self.editor_vars["quantity"].set(product.quantity)
            self.description_text.delete("1.0", "end")
            self.description_text.insert("1.0", product.description)
            self.description_text.edit_modified(False)
            if product.issues:
                lines = [f"{issue.severity.upper():7} {issue.field:14} {issue.message}" for issue in product.issues]
                self.set_validation_text("\n".join(lines))
            else:
                self.set_validation_text("Ready for export.")
            self.editor_dirty = False
        finally:
            self.populating_editor = False

    def set_validation_text(self, value: str) -> None:
        self.validation_text.configure(state="normal")
        self.validation_text.delete("1.0", "end")
        self.validation_text.insert("1.0", value)
        self.validation_text.configure(state="disabled")

    def persist_editor_state(self, silent: bool = False) -> bool:
        if self.populating_editor or not self.selected_product_id:
            self.logger.debug(
                "Skipping persist_editor_state. populating_editor=%s selected_product_id=%s",
                self.populating_editor,
                self.selected_product_id,
            )
            return False
        if not self.editor_dirty:
            self.logger.debug("Skipping persist_editor_state because editor is not dirty for product %s.", self.selected_product_id)
            return False
        self._log_editor_snapshot(self.selected_product_id)
        self.overrides[self.selected_product_id] = ProductOverride(
            included=self.include_var.get(),
            name=self.editor_vars["name"].get().strip(),
            link=self.editor_vars["link"].get().strip(),
            image=self.editor_vars["image"].get().strip(),
            additional_images=self.editor_vars["additional_images"].get().strip(),
            category=self.editor_vars["category"].get().strip(),
            price=self.editor_vars["price"].get().strip(),
            vat=self.editor_vars["vat"].get().strip(),
            manufacturer=self.editor_vars["manufacturer"].get().strip(),
            mpn=self.editor_vars["mpn"].get().strip(),
            ean=self.editor_vars["ean"].get().strip(),
            availability=self.editor_vars["availability"].get().strip(),
            weight=self.editor_vars["weight"].get().strip(),
            color=self.editor_vars["color"].get().strip(),
            size=self.editor_vars["size"].get().strip(),
            description=self.description_text.get("1.0", "end").strip(),
            quantity=self.editor_vars["quantity"].get().strip(),
        )
        self.refresh_products()
        updated = self.find_product(self.selected_product_id)
        if updated:
            self.populate_editor(updated)
            self._log_product_issues(updated, "Applied product changes")
            if updated.error_count and not silent:
                messagebox.showwarning("Still needs fixes", f"{updated.name} still has {updated.error_count} blocking validation issue(s).")
        else:
            self.logger.warning("Product %s disappeared after persisting editor state.", self.selected_product_id)
        return True

    def apply_product_changes(self) -> None:
        if not self.selected_product_id:
            self.logger.info("Apply product changes requested without a selected product.")
            messagebox.showinfo("No product selected", "Select a product first.")
            return
        if self.persist_editor_state():
            self._set_status(f"Updated product {self.selected_product_id}.")

    def reset_product_changes(self) -> None:
        if not self.selected_product_id:
            self.logger.info("Reset overrides requested without a selected product.")
            return
        self.overrides.pop(self.selected_product_id, None)
        self.refresh_products()
        self._set_status(f"Cleared overrides for product {self.selected_product_id}.")

    def generate_xml(self) -> None:
        if not self.csv_path or not self.source_products:
            self.logger.info("Generate XML requested before a CSV was loaded.")
            messagebox.showinfo("No CSV loaded", "Load a WooCommerce CSV before exporting.")
            return
        self.persist_editor_state(silent=True)
        self.refresh_products()
        ready = [product for product in self.resolved_products if product.included and product.error_count == 0]
        invalid = [product for product in self.resolved_products if product.included and product.error_count > 0]
        if not ready:
            self._log_validation_snapshot("Export blocked because no products are ready", max_products=20)
            messagebox.showerror("Nothing to export", "There are no included products without blocking validation issues.")
            return
        if invalid:
            self._log_validation_snapshot("Export contains invalid products", max_products=20)
            proceed = messagebox.askyesno(
                "Skip invalid products?",
                f"{len(invalid)} included product(s) still have blocking validation issues.\n\nContinue with the {len(ready)} ready product(s) only?",
            )
            if not proceed:
                self.logger.info("Export cancelled by user after validation warning.")
                return
        default_path = self.csv_path.with_name(f"{self.csv_path.stem}-skroutz.xml")
        target = filedialog.asksaveasfilename(
            title="Save Skroutz XML",
            initialdir=str(default_path.parent),
            initialfile=default_path.name,
            defaultextension=".xml",
            filetypes=[("XML files", "*.xml"), ("All files", "*.*")],
        )
        if not target:
            self.logger.info("Save XML dialog was cancelled.")
            return
        try:
            destination = export_xml(ready, target, self._current_settings().root_element)
            self._set_status(f"Exported {len(ready)} product(s) to {destination.name}.")
            self.logger.info("Exported XML feed to %s with %d ready products and %d skipped invalid products.", destination, len(ready), len(invalid))
            messagebox.showinfo(
                "XML exported",
                f"Feed created at:\n{destination}\n\nExported: {len(ready)}\nSkipped: {len(invalid)}\n\nYou can upload it to {VALIDATOR_URL}",
            )
        except Exception as exc:
            self.logger.exception("Could not export XML to %s", target)
            messagebox.showerror("Could not export XML", str(exc))

    def report_callback_exception(self, exc: type[BaseException], val: BaseException, tb: object) -> None:
        self.logger.exception("Unhandled Tkinter exception", exc_info=(exc, val, tb))
        messagebox.showerror(
            "Unexpected error",
            f"An unexpected error occurred.\n\nDetails were written to:\n{self.log_path}",
        )

    def on_close(self) -> None:
        self.logger.info("Application window is closing.")
        if self.log_handler is not None:
            self.logger.removeHandler(self.log_handler)
            self.log_handler.close()
            self.log_handler = None
        self.destroy()


def main() -> None:
    app = FeedBuilderApp()
    app.mainloop()
