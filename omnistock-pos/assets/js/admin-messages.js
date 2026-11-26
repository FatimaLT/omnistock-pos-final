const messageImageInput = document.querySelector('#warehouse-message-image');
const messageImagePreview = document.querySelector('#warehouse-message-image-preview');
const messagePreviewEmpty = document.querySelector('#warehouse-message-image-empty');
const messageImageFeedback = document.querySelector('#warehouse-message-image-feedback');
let messagePreviewUrl = null;

messageImageInput.addEventListener('change', () => {
  if (messagePreviewUrl) URL.revokeObjectURL(messagePreviewUrl);
  messagePreviewUrl = null;
  messageImageFeedback.textContent = '';
  const file = messageImageInput.files?.[0];
  if (!file) {
    messageImagePreview.removeAttribute('src');
    messageImagePreview.hidden = true;
    messagePreviewEmpty.hidden = false;
    return;
  }
  if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
    messageImageInput.value = '';
    messageImagePreview.removeAttribute('src');
    messageImagePreview.hidden = true;
    messagePreviewEmpty.hidden = false;
    messageImageFeedback.textContent = 'Elige una imagen PNG, JPG o WEBP de máximo 5 MB.';
    return;
  }
  messagePreviewUrl = URL.createObjectURL(file);
  messageImagePreview.src = messagePreviewUrl;
  messageImagePreview.hidden = false;
  messagePreviewEmpty.hidden = true;
});
