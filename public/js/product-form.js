(function () {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    var thumbnailInput = document.getElementById('thumbnail');
    var thumbnailPreview = document.getElementById('thumbnail-preview');

    var imagesInput = document.getElementById('images');
    var newImagesPreview = document.getElementById('new-images-preview');
    var existingImagesContainer = document.getElementById('existing-images');

    function showToast(message, type) {
        var stack = document.getElementById('toast-stack');
        if (!stack) {
            return;
        }
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + (type || 'success');
        toast.textContent = message;
        stack.appendChild(toast);
        setTimeout(function () {
            toast.classList.add('is-leaving');
            setTimeout(function () {
                toast.remove();
            }, 200);
        }, 3000);
    }

    thumbnailInput.addEventListener('change', function () {
        var file = thumbnailInput.files[0];
        if (file) {
            thumbnailPreview.src = URL.createObjectURL(file);
            thumbnailPreview.style.display = 'block';
        }
    });

    imagesInput.addEventListener('change', function () {
        var files = Array.prototype.slice.call(imagesInput.files);
        newImagesPreview.innerHTML = '';

        files.forEach(function (file) {
            var item = document.createElement('div');
            item.className = 'gallery-item';
            item.innerHTML = '<img src="' + URL.createObjectURL(file) + '" alt="">';
            newImagesPreview.appendChild(item);
        });
    });

    if (existingImagesContainer) {
        existingImagesContainer.addEventListener('click', function (event) {
            var removeBtn = event.target.closest('.gallery-item-remove');
            if (!removeBtn) {
                return;
            }

            fetch(removeBtn.dataset.url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
                .then(function (res) {
                    return res.json();
                })
                .then(function (body) {
                    removeBtn.closest('.gallery-item').remove();
                    showToast(body.message, 'success');
                });
        });
    }
})();
