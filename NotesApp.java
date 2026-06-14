// NotesApp.java - Заметки с тегами на Java Swing
import javax.swing.*;
import java.awt.*;
import java.awt.event.*;
import java.io.*;
import java.nio.file.*;
import java.util.*;
import java.util.List;
import com.fasterxml.jackson.databind.ObjectMapper;
import com.fasterxml.jackson.core.type.TypeReference;

class Note {
    public int id;
    public String title;
    public String content;
    public List<String> tags;
    public String color;
    public String created;
    public String modified;
    public Note() {}
    public Note(int id, String title, String content, List<String> tags, String color, String created, String modified) {
        this.id = id; this.title = title; this.content = content; this.tags = tags; this.color = color; this.created = created; this.modified = modified;
    }
}

public class NotesApp extends JFrame {
    private List<Note> notes = new ArrayList<>();
    private int nextId = 1;
    private final String DATA_FILE = "notes.json";
    private ObjectMapper mapper = new ObjectMapper();
    private DefaultListModel<String> noteListModel;
    private JList<String> noteList;
    private JTextArea contentArea;
    private JTextField titleField, tagsField;
    private JPanel colorPanel;
    private String currentColor = "#ffffff";
    private int currentNoteId = -1;
    private JLabel statusLabel;

    public NotesApp() {
        loadNotes();
        initUI();
    }

    private void loadNotes() {
        File f = new File(DATA_FILE);
        if (f.exists()) {
            try {
                String json = new String(Files.readAllBytes(Paths.get(DATA_FILE)));
                notes = mapper.readValue(json, new TypeReference<List<Note>>(){});
                nextId = notes.stream().mapToInt(n -> n.id).max().orElse(0) + 1;
            } catch (Exception e) { e.printStackTrace(); }
        } else {
            notes = new ArrayList<>();
            nextId = 1;
        }
    }

    private void saveNotes() {
        try {
            mapper.writeValue(new File(DATA_FILE), notes);
        } catch (Exception e) { e.printStackTrace(); }
    }

    private void initUI() {
        setTitle("Заметки с тегами");
        setSize(1000, 600);
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        setLayout(new BorderLayout());

        // Левая панель
        JPanel leftPanel = new JPanel(new BorderLayout());
        leftPanel.setPreferredSize(new Dimension(250, 0));
        leftPanel.setBackground(new Color(44,62,80));
        JTextField searchField = new JTextField();
        searchField.addKeyListener(new KeyAdapter() {
            public void keyReleased(KeyEvent e) { refreshNoteList(searchField.getText()); }
        });
        leftPanel.add(searchField, BorderLayout.NORTH);
        noteListModel = new DefaultListModel<>();
        noteList = new JList<>(noteListModel);
        noteList.addListSelectionListener(e -> {
            if (!e.getValueIsAdjusting()) {
                int idx = noteList.getSelectedIndex();
                if (idx != -1) {
                    int id = (int) noteList.getClientProperty("id_" + idx);
                    showNote(id);
                }
            }
        });
        leftPanel.add(new JScrollPane(noteList), BorderLayout.CENTER);
        JButton newBtn = new JButton("Новая заметка");
        newBtn.addActionListener(e -> newNote());
        leftPanel.add(newBtn, BorderLayout.SOUTH);
        add(leftPanel, BorderLayout.WEST);

        // Правая панель редактора
        JPanel editorPanel = new JPanel(new BorderLayout());
        editorPanel.setBackground(Color.WHITE);
        JPanel topEditor = new JPanel(new FlowLayout(FlowLayout.LEFT));
        topEditor.add(new JLabel("Заголовок:"));
        titleField = new JTextField(30);
        topEditor.add(titleField);
        topEditor.add(new JLabel("Теги:"));
        tagsField = new JTextField(20);
        topEditor.add(tagsField);
        editorPanel.add(topEditor, BorderLayout.NORTH);

        contentArea = new JTextArea();
        contentArea.setFont(new Font("Monospaced", Font.PLAIN, 12));
        editorPanel.add(new JScrollPane(contentArea), BorderLayout.CENTER);

        // Цвета
        colorPanel = new JPanel(new FlowLayout(FlowLayout.LEFT));
        String[] colors = {"#ffffff","#ffcccc","#ccffcc","#ccccff","#ffffcc","#ffccff"};
        for (String col : colors) {
            JButton btn = new JButton();
            btn.setBackground(Color.decode(col));
            btn.setPreferredSize(new Dimension(30,30));
            btn.addActionListener(e -> {
                currentColor = col;
                editorPanel.setBackground(Color.decode(col));
                updateCurrentNote();
            });
            colorPanel.add(btn);
        }
        editorPanel.add(colorPanel, BorderLayout.SOUTH);

        statusLabel = new JLabel(" ");
        editorPanel.add(statusLabel, BorderLayout.AFTER_LAST_LINE);
        add(editorPanel, BorderLayout.CENTER);

        JMenuBar menuBar = new JMenuBar();
        JMenu fileMenu = new JMenu("Файл");
        JMenuItem exportItem = new JMenuItem("Экспорт JSON");
        exportItem.addActionListener(e -> exportJSON());
        JMenuItem importItem = new JMenuItem("Импорт JSON");
        importItem.addActionListener(e -> importJSON());
        fileMenu.add(exportItem);
        fileMenu.add(importItem);
        menuBar.add(fileMenu);
        setJMenuBar(menuBar);

        refreshNoteList("");
    }

    private void refreshNoteList(String search) {
        noteListModel.clear();
        List<Note> filtered = notes;
        if (!search.isEmpty()) {
            String s = search.toLowerCase();
            filtered = new ArrayList<>();
            for (Note n : notes) {
                if (n.title.toLowerCase().contains(s) || n.content.toLowerCase().contains(s) ||
                    n.tags.stream().anyMatch(t -> t.toLowerCase().contains(s))) {
                    filtered.add(n);
                }
            }
        }
        for (int i=0; i<filtered.size(); i++) {
            Note n = filtered.get(i);
            noteListModel.addElement(n.title + " [" + String.join(",", n.tags) + "]");
            noteList.putClientProperty("id_"+i, n.id);
        }
    }

    private void showNote(int id) {
        Note n = notes.stream().filter(x -> x.id == id).findFirst().orElse(null);
        if (n != null) {
            currentNoteId = id;
            titleField.setText(n.title);
            tagsField.setText(String.join(" ", n.tags));
            contentArea.setText(n.content);
            currentColor = n.color;
            getContentPane().getComponent(1).setBackground(Color.decode(n.color));
            statusLabel.setText("Загружено: " + n.modified);
        }
    }

    private void updateCurrentNote() {
        if (currentNoteId == -1) return;
        for (int i=0; i<notes.size(); i++) {
            if (notes.get(i).id == currentNoteId) {
                notes.get(i).title = titleField.getText().trim();
                notes.get(i).tags = Arrays.asList(tagsField.getText().trim().split("\\s+"));
                notes.get(i).content = contentArea.getText();
                notes.get(i).color = currentColor;
                notes.get(i).modified = java.time.LocalDateTime.now().toString();
                saveNotes();
                refreshNoteList("");
                statusLabel.setText("Сохранено " + java.time.LocalTime.now());
                break;
            }
        }
    }

    private void newNote() {
        int newId = nextId++;
        String now = java.time.LocalDateTime.now().toString();
        Note newNote = new Note(newId, "Новая заметка", "", new ArrayList<>(), "#ffffff", now, now);
        notes.add(newNote);
        saveNotes();
        refreshNoteList("");
        currentNoteId = newId;
        showNote(newId);
    }

    private void exportJSON() {
        JFileChooser fc = new JFileChooser();
        if (fc.showSaveDialog(this) == JFileChooser.APPROVE_OPTION) {
            try {
                mapper.writeValue(fc.getSelectedFile(), notes);
                JOptionPane.showMessageDialog(this, "Экспортировано");
            } catch (Exception e) { e.printStackTrace(); }
        }
    }

    private void importJSON() {
        JFileChooser fc = new JFileChooser();
        if (fc.showOpenDialog(this) == JFileChooser.APPROVE_OPTION) {
            try {
                List<Note> imported = mapper.readValue(fc.getSelectedFile(), new TypeReference<List<Note>>(){});
                notes.addAll(imported);
                nextId = notes.stream().mapToInt(n -> n.id).max().orElse(0) + 1;
                saveNotes();
                refreshNoteList("");
                JOptionPane.showMessageDialog(this, "Импортировано " + imported.size() + " заметок");
            } catch (Exception e) { e.printStackTrace(); }
        }
    }

    public static void main(String[] args) {
        SwingUtilities.invokeLater(() -> new NotesApp().setVisible(true));
    }
}
