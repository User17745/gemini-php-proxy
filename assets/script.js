async function submitPrompt() {
    const button = document.querySelector('button');
    const prompt = document.getElementById('promptInput').value;
    const file = document.getElementById('imageInput').files[0];
    const pageType = document.getElementById('pageType').value;
    const sectionType = document.getElementById('sectionType').value;
    const language = document.getElementById('language').value;
    const layoutDir = document.getElementById('layoutDir').value;
    const tone = document.getElementById('tone').value;

    if (!prompt || prompt.trim() === '') {
        document.getElementById('codeView').textContent = 'Prompt is required and must be a non-empty string.';
        document.getElementById('renderedView').srcdoc = '';
        return;
    }

    button.disabled = true;
    button.classList.add('loading');

    const images = [];
    if (file) {
        const base64 = await toBase64(file);
        images.push({
            mimeType: file.type,
            data: base64.split(',')[1] // remove "data:image/jpeg;base64,"
        });
    }

    const payload = {
        prompt: prompt.trim(),
        sectionType,
        pageType,
        language,
        layoutDir,
        tone,
        images
    };

    try {
        const res = await fetch('widget-gen.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        const data = await res.json();
        let responseText = data?.candidates?.[0]?.content?.parts?.[0]?.text || 'No response.';

        // Remove code block markers (e.g., ```html, ```js, etc.)
        responseText = responseText.replace(/```[a-z]*\n?/g, '').replace(/```/g, '');

        document.getElementById('codeView').textContent = responseText;
        document.getElementById('renderedView').srcdoc = responseText;
    } catch (error) {
        document.getElementById('codeView').textContent = `Error: ${error.message}`;
        document.getElementById('renderedView').srcdoc = '';
    } finally {
        button.disabled = false;
        button.classList.remove('loading');
    }
}

function toBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(file);
    });
}

function copyToClipboard() {
    const codeView = document.getElementById('codeView');
    const textToCopy = codeView.textContent || codeView.innerText;

    navigator.clipboard.writeText(textToCopy).then(() => {
        alert('Code copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy: ', err);
    });
}

document.getElementById('imageInput').addEventListener('change', function () {
    const fileLabel = document.querySelector('.file-label');
    if (this.files && this.files.length > 0) {
        fileLabel.classList.add('selected');
    } else {
        fileLabel.classList.remove('selected');
    }
});

document.getElementById('promptInput').addEventListener('keydown', function (event) {
    if (event.key === 'Enter') {
        event.preventDefault();
        submitPrompt();
    }
});