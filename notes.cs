// notes.cs - Заметки с тегами на C# Windows Forms
using System;
using System.Collections.Generic;
using System.Drawing;
using System.IO;
using System.Linq;
using System.Text.Json;
using System.Windows.Forms;

namespace NotesApp
{
    public class Note
    {
        public int Id { get; set; }
        public string Title { get; set; }
        public string Content { get; set; }
        public List<string> Tags { get; set; }
        public string Color { get; set; }
        public string Created { get; set; }
        public string Modified { get; set; }
    }

    public class MainForm : Form
    {
        private List<Note> notes = new List<Note>();
        private int nextId = 1;
        private string dataFile = "notes.json";
        private ListBox noteList;
        private TextBox titleBox, tagsBox, searchBox;
        private RichTextBox contentBox;
        private Panel colorPanel;
        private Label statusLabel;
        private string currentColor = "#ffffff";
        private int currentNoteId = -1;

        public MainForm()
        {
            Text = "Заметки с тегами";
            Size = new Size(1000, 600);
            LoadNotes();
            InitializeUI();
        }

        private void LoadNotes()
        {
            if (File.Exists(dataFile))
            {
                string json = File.ReadAllText(dataFile);
                notes = JsonSerializer.Deserialize<List<Note>>(json) ?? new List<Note>();
                nextId = notes.Count > 0 ? notes.Max(n => n.Id) + 1 : 1;
            }
            else
            {
                notes = new List<Note>();
                nextId = 1;
            }
        }

        private void SaveNotes()
        {
            string json = JsonSerializer.Serialize(notes, new JsonSerializerOptions { WriteIndented = true });
            File.WriteAllText(dataFile, json);
        }

        private void InitializeUI()
        {
            // Левая панель
            var leftPanel = new Panel { Dock = DockStyle.Left, Width = 250, BackColor = Color.FromArgb(44,62,80) };
            searchBox = new TextBox { Dock = DockStyle.Top, Margin = new Padding(5) };
            searchBox.TextChanged += (s, e) => RefreshNoteList();
            leftPanel.Controls.Add(searchBox);
            noteList = new ListBox { Dock = DockStyle.Fill, BackColor = Color.White };
            noteList.SelectedIndexChanged += (s, e) => {
                if (noteList.SelectedIndex != -1 && noteList.SelectedItem != null)
                {
                    int id = (int)noteList.SelectedItem.GetType().GetProperty("Id")?.GetValue(noteList.SelectedItem, null);
                    ShowNote(id);
                }
            };
            leftPanel.Controls.Add(noteList);
            var newBtn = new Button { Text = "➕ Новая заметка", Dock = DockStyle.Bottom, Height = 35, BackColor = Color.DodgerBlue, ForeColor = Color.White };
            newBtn.Click += (s, e) => NewNote();
            leftPanel.Controls.Add(newBtn);
            Controls.Add(leftPanel);

            // Правая панель
            var rightPanel = new Panel { Dock = DockStyle.Fill, BackColor = Color.White };
            titleBox = new TextBox { Dock = DockStyle.Top, Height = 35, Font = new Font("Arial", 12), Margin = new Padding(5) };
            tagsBox = new TextBox { Dock = DockStyle.Top, Height = 30, Margin = new Padding(5) };
            contentBox = new RichTextBox { Dock = DockStyle.Fill, Font = new Font("Consolas", 11) };
            colorPanel = new Panel { Dock = DockStyle.Bottom, Height = 50 };
            string[] colors = { "#ffffff", "#ffcccc", "#ccffcc", "#ccccff", "#ffffcc", "#ffccff" };
            foreach (string col in colors)
            {
                var btn = new Button { BackColor = ColorTranslator.FromHtml(col), Width = 40, Height = 40, FlatStyle = FlatStyle.Flat };
                btn.Click += (s, e) => {
                    currentColor = col;
                    rightPanel.BackColor = ColorTranslator.FromHtml(col);
                    UpdateCurrentNote();
                };
                colorPanel.Controls.Add(btn);
            }
            statusLabel = new Label { Dock = DockStyle.Bottom, Text = "", Height = 20, TextAlign = ContentAlignment.MiddleLeft };
            rightPanel.Controls.Add(contentBox);
            rightPanel.Controls.Add(tagsBox);
            rightPanel.Controls.Add(titleBox);
            rightPanel.Controls.Add(colorPanel);
            rightPanel.Controls.Add(statusLabel);
            Controls.Add(rightPanel);

            // Меню
            var menu = new MenuStrip();
            var fileMenu = new ToolStripMenuItem("Файл");
            var exportItem = new ToolStripMenuItem("Экспорт JSON");
            exportItem.Click += (s, e) => ExportJSON();
            var importItem = new ToolStripMenuItem("Импорт JSON");
            importItem.Click += (s, e) => ImportJSON();
            fileMenu.DropDownItems.Add(exportItem);
            fileMenu.DropDownItems.Add(importItem);
            menu.Items.Add(fileMenu);
            MainMenuStrip = menu;
            Controls.Add(menu);

            RefreshNoteList();
        }

        private void RefreshNoteList()
        {
            noteList.Items.Clear();
            string search = searchBox.Text.ToLower();
            var filtered = notes;
            if (!string.IsNullOrEmpty(search))
                filtered = notes.Where(n => n.Title.ToLower().Contains(search) || n.Content.ToLower().Contains(search) || n.Tags.Any(t => t.ToLower().Contains(search))).ToList();
            foreach (var n in filtered)
            {
                noteList.Items.Add(new { Id = n.Id, Display = $"{n.Title} [{string.Join(",", n.Tags)}]" });
            }
            noteList.DisplayMember = "Display";
            noteList.ValueMember = "Id";
        }

        private void ShowNote(int id)
        {
            var note = notes.FirstOrDefault(n => n.Id == id);
            if (note != null)
            {
                currentNoteId = id;
                titleBox.Text = note.Title;
                tagsBox.Text = string.Join(" ", note.Tags);
                contentBox.Text = note.Content;
                currentColor = note.Color;
                (Controls[1] as Panel).BackColor = ColorTranslator.FromHtml(note.Color);
                statusLabel.Text = $"Изменена: {note.Modified}";
            }
        }

        private void UpdateCurrentNote()
        {
            if (currentNoteId == -1) return;
            var note = notes.FirstOrDefault(n => n.Id == currentNoteId);
            if (note != null)
            {
                note.Title = titleBox.Text.Trim();
                note.Tags = tagsBox.Text.Split(new[] { ' ' }, StringSplitOptions.RemoveEmptyEntries).ToList();
                note.Content = contentBox.Text;
                note.Color = currentColor;
                note.Modified = DateTime.Now.ToString("o");
                SaveNotes();
                RefreshNoteList();
                statusLabel.Text = $"Сохранено {DateTime.Now:t}";
            }
        }

        private void NewNote()
        {
            var newNote = new Note
            {
                Id = nextId++,
                Title = "Новая заметка",
                Content = "",
                Tags = new List<string>(),
                Color = "#ffffff",
                Created = DateTime.Now.ToString("o"),
                Modified = DateTime.Now.ToString("o")
            };
            notes.Add(newNote);
            SaveNotes();
            RefreshNoteList();
            ShowNote(newNote.Id);
        }

        private void ExportJSON()
        {
            var saveDialog = new SaveFileDialog { Filter = "JSON files|*.json", DefaultExt = "json" };
            if (saveDialog.ShowDialog() == DialogResult.OK)
            {
                string json = JsonSerializer.Serialize(notes, new JsonSerializerOptions { WriteIndented = true });
                File.WriteAllText(saveDialog.FileName, json);
                MessageBox.Show("Экспортировано");
            }
        }

        private void ImportJSON()
        {
            var openDialog = new OpenFileDialog { Filter = "JSON files|*.json" };
            if (openDialog.ShowDialog() == DialogResult.OK)
            {
                string json = File.ReadAllText(openDialog.FileName);
                var imported = JsonSerializer.Deserialize<List<Note>>(json);
                if (imported != null)
                {
                    notes.AddRange(imported);
                    nextId = notes.Max(n => n.Id) + 1;
                    SaveNotes();
                    RefreshNoteList();
                    MessageBox.Show($"Импортировано {imported.Count} заметок");
                }
            }
        }
    }

    static class Program
    {
        [STAThread]
        static void Main()
        {
            Application.EnableVisualStyles();
            Application.Run(new MainForm());
        }
    }
}
