<?php
// notes.php - Заметки с тегами на PHP + JSON (или MySQL)
session_start();
$dataFile = 'notes.json';

function loadNotes() {
    global $dataFile;
    if (file_exists($dataFile)) {
        $json = file_get_contents($dataFile);
        return json_decode($json, true) ?? [];
    }
    return [];
}

function saveNotes($notes) {
    global $dataFile;
    file_put_contents($dataFile, json_encode(array_values($notes), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Обработка AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $notes = loadNotes();
    if ($action === 'getAll') {
        $search = $_GET['search'] ?? '';
        $tag = $_GET['tag'] ?? '';
        $filtered = $notes;
        if ($search) {
            $search = strtolower($search);
            $filtered = array_filter($filtered, function($n) use ($search) {
                return strpos(strtolower($n['title']), $search) !== false ||
                       strpos(strtolower($n['content']), $search) !== false ||
                       array_reduce($n['tags'] ?? [], fn($carry, $t) => $carry || strpos(strtolower($t), $search) !== false, false);
            });
        } elseif ($tag) {
            $filtered = array_filter($filtered, fn($n) => in_array($tag, $n['tags'] ?? []));
        }
        echo json_encode(array_values($filtered));
    } elseif ($action === 'save') {
        $id = (int)$_POST['id'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $tags = explode(' ', $_POST['tags']);
        $color = $_POST['color'];
        $now = date('c');
        if ($id === 0) {
            $newId = count($notes) > 0 ? max(array_column($notes, 'id')) + 1 : 1;
            $notes[] = ['id' => $newId, 'title' => $title, 'content' => $content, 'tags' => $tags, 'color' => $color, 'created' => $now, 'modified' => $now];
        } else {
            foreach ($notes as &$n) {
                if ($n['id'] == $id) {
                    $n['title'] = $title;
                    $n['content'] = $content;
                    $n['tags'] = $tags;
                    $n['color'] = $color;
                    $n['modified'] = $now;
                    break;
                }
            }
        }
        saveNotes($notes);
        echo json_encode(['success' => true]);
    } elseif ($action === 'delete') {
        $id = (int)$_POST['id'];
        $notes = array_filter($notes, fn($n) => $n['id'] != $id);
        saveNotes($notes);
        echo json_encode(['success' => true]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Заметки с тегами PHP</title>
    <style>
        * { box-sizing: border-box; font-family: system-ui; }
        body { margin: 0; background: #f0f2f5; display: flex; height: 100vh; }
        .sidebar { width: 280px; background: #2c3e50; color: white; padding: 15px; display: flex; flex-direction: column; }
        .sidebar input { width: 100%; padding: 8px; margin-bottom: 15px; border-radius: 20px; border: none; }
        .note-list { flex: 1; overflow-y: auto; }
        .note-item { background: #34495e; margin-bottom: 8px; padding: 10px; border-radius: 12px; cursor: pointer; }
        .note-item:hover { background: #3b5998; }
        .tag-cloud { margin-top: 20px; border-top: 1px solid #466; padding-top: 10px; }
        .tag-btn { background: #1abc9c; border: none; color: white; border-radius: 20px; padding: 4px 10px; margin: 3px; cursor: pointer; font-size: 12px; }
        .editor { flex: 1; background: white; display: flex; flex-direction: column; padding: 20px; }
        .editor input, .editor textarea { width: 100%; margin-bottom: 12px; padding: 10px; border-radius: 12px; border: 1px solid #ddd; }
        .editor textarea { flex: 1; resize: none; }
        .toolbar { display: flex; gap: 10px; margin-bottom: 15px; }
        button { background: #3498db; color: white; border: none; padding: 8px 16px; border-radius: 20px; cursor: pointer; }
        button.danger { background: #e74c3c; }
        .color-options { display: flex; gap: 8px; margin-bottom: 15px; }
        .color-opt { width: 30px; height: 30px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; }
        .color-opt.selected { border-color: #2c3e50; transform: scale(1.1); }
        .status { font-size: 12px; color: gray; margin-top: 5px; }
    </style>
</head>
<body>
<div class="sidebar">
    <h3>📝 Заметки</h3>
    <input type="text" id="search" placeholder="Поиск...">
    <div class="note-list" id="noteList"></div>
    <div class="tag-cloud" id="tagCloud"></div>
    <button id="newBtn">➕ Новая</button>
</div>
<div class="editor" id="editor">
    <div class="toolbar">
        <button id="deleteBtn" class="danger">🗑 Удалить</button>
        <button id="exportBtn">📎 Экспорт JSON</button>
        <input type="file" id="importInput" accept=".json" style="display:none">
        <button id="importBtn">📂 Импорт JSON</button>
    </div>
    <input type="text" id="title" placeholder="Заголовок">
    <input type="text" id="tags" placeholder="Теги через пробел">
    <div class="color-options" id="colorOptions"></div>
    <textarea id="content" placeholder="Содержание..."></textarea>
    <div class="status" id="status"></div>
</div>
<script>
    let currentId = null;
    const colors = ["#ffffff","#ffcccc","#ccffcc","#ccccff","#ffffcc","#ffccff"];
    let currentColor = "#ffffff";

    async function fetchNotes(search='', tag='') {
        let url = `?action=getAll&search=${encodeURIComponent(search)}&tag=${encodeURIComponent(tag)}`;
        let res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        return await res.json();
    }

    async function renderSidebar() {
        let search = document.getElementById('search').value;
        let notes = await fetchNotes(search, '');
        const container = document.getElementById('noteList');
        container.innerHTML = '';
        notes.forEach(n => {
            const div = document.createElement('div');
            div.className = 'note-item';
            if (currentId === n.id) div.style.background = '#3b5998';
            div.innerHTML = `<div><strong>${escapeHtml(n.title.substring(0,40))}</strong></div>
                             <div style="font-size:11px">${(n.tags||[]).map(t=>'#'+t).join(' ')}</div>`;
            div.onclick = () => { currentId = n.id; displayNote(n); renderSidebar(); };
            container.appendChild(div);
        });
        // облако тегов
        const tagCount = new Map();
        notes.forEach(n => (n.tags||[]).forEach(t => tagCount.set(t, (tagCount.get(t)||0)+1)));
        const tagDiv = document.getElementById('tagCloud');
        tagDiv.innerHTML = '<strong>🏷️ Теги</strong><br>';
        for (let [tag, cnt] of Array.from(tagCount.entries()).sort((a,b)=>b[1]-a[1]).slice(0,15)) {
            const btn = document.createElement('button');
            btn.className = 'tag-btn';
            btn.textContent = `${tag} (${cnt})`;
            btn.onclick = () => { document.getElementById('search').value = tag; renderSidebar(); };
            tagDiv.appendChild(btn);
        }
    }

    function displayNote(note) {
        document.getElementById('title').value = note.title;
        document.getElementById('tags').value = (note.tags||[]).join(' ');
        document.getElementById('content').value = note.content;
        currentColor = note.color || "#ffffff";
        document.getElementById('editor').style.backgroundColor = currentColor;
        document.querySelectorAll('.color-opt').forEach(el => {
            if (el.dataset.color === currentColor) el.classList.add('selected');
            else el.classList.remove('selected');
        });
        document.getElementById('status').innerText = `Изменена: ${note.modified || ''}`;
        currentId = note.id;
    }

    async function saveCurrent() {
        if (!currentId) return;
        const data = new URLSearchParams();
        data.append('action', 'save');
        data.append('id', currentId);
        data.append('title', document.getElementById('title').value);
        data.append('tags', document.getElementById('tags').value);
        data.append('content', document.getElementById('content').value);
        data.append('color', currentColor);
        await fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: data });
        renderSidebar();
        document.getElementById('status').innerText = 'Сохранено ' + new Date().toLocaleTimeString();
    }

    async function newNote() {
        const data = new URLSearchParams();
        data.append('action', 'save');
        data.append('id', '0');
        data.append('title', 'Новая заметка');
        data.append('tags', '');
        data.append('content', '');
        data.append('color', '#ffffff');
        await fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: data });
        await renderSidebar();
        // выбрать последнюю
        let notes = await fetchNotes('','');
        if (notes.length) currentId = notes[notes.length-1].id;
        await renderSidebar();
        let updated = await fetchNotes('','');
        let last = updated.find(n => n.id === currentId);
        if (last) displayNote(last);
    }

    async function deleteNote() {
        if (!currentId) return;
        if (confirm('Удалить заметку?')) {
            const data = new URLSearchParams();
            data.append('action', 'delete');
            data.append('id', currentId);
            await fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: data });
            currentId = null;
            document.getElementById('title').value = '';
            document.getElementById('tags').value = '';
            document.getElementById('content').value = '';
            renderSidebar();
        }
    }

    function initColorPicker() {
        const container = document.getElementById('colorOptions');
        container.innerHTML = '';
        colors.forEach(col => {
            const div = document.createElement('div');
            div.className = 'color-opt';
            div.style.backgroundColor = col;
            div.dataset.color = col;
            div.onclick = () => {
                document.querySelectorAll('.color-opt').forEach(c => c.classList.remove('selected'));
                div.classList.add('selected');
                currentColor = col;
                document.getElementById('editor').style.backgroundColor = col;
                saveCurrent();
            };
            container.appendChild(div);
        });
    }

    function exportJSON() {
        // сделать запрос на получение всех заметок и скачать
        fetchNotes('','').then(notes => {
            const blob = new Blob([JSON.stringify(notes, null, 2)], {type: 'application/json'});
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'notes_backup.json';
            a.click();
            URL.revokeObjectURL(a.href);
        });
    }
    function importJSON(file) {
        const reader = new FileReader();
        reader.onload = async (e) => {
            const imported = JSON.parse(e.target.result);
            for (let note of imported) {
                const data = new URLSearchParams();
                data.append('action', 'save');
                data.append('id', '0');
                data.append('title', note.title);
                data.append('tags', (note.tags||[]).join(' '));
                data.append('content', note.content);
                data.append('color', note.color || '#ffffff');
                await fetch('', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: data });
            }
            renderSidebar();
            alert('Импорт завершён');
        };
        reader.readAsText(file);
    }

    document.getElementById('search').addEventListener('input', () => renderSidebar());
    document.getElementById('newBtn').onclick = newNote;
    document.getElementById('deleteBtn').onclick = deleteNote;
    document.getElementById('exportBtn').onclick = exportJSON;
    document.getElementById('importBtn').onclick = () => document.getElementById('importInput').click();
    document.getElementById('importInput').onchange = (e) => importJSON(e.target.files[0]);
    document.getElementById('title').addEventListener('input', saveCurrent);
    document.getElementById('tags').addEventListener('input', saveCurrent);
    document.getElementById('content').addEventListener('input', saveCurrent);
    setInterval(saveCurrent, 30000);
    function escapeHtml(str) { return str.replace(/[&<>]/g, function(m){if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }
    initColorPicker();
    renderSidebar();
</script>
</body>
</html>
