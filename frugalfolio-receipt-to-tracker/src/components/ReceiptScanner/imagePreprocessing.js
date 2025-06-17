// Basic image preprocessing for receipt OCR
export const preprocessImage = async (imageData) => {
    return new Promise((resolve) => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        const img = new Image();

        img.onload = () => {
            // Set canvas size to match image
            canvas.width = img.width;
            canvas.height = img.height;

            // Draw original image
            ctx.drawImage(img, 0, 0);

            // Get image data for processing
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const data = imageData.data;

            // 1. Convert to grayscale
            for (let i = 0; i < data.length; i += 4) {
                const avg = (data[i] + data[i + 1] + data[i + 2]) / 3;
                data[i] = data[i + 1] = data[i + 2] = avg;
            }

            // 2. Simple contrast enhancement
            for (let i = 0; i < data.length; i += 4) {
                const contrast = 1.2; // Subtle contrast increase
                const gray = data[i];
                const newVal = Math.min(255, Math.max(0, gray * contrast));
                data[i] = data[i + 1] = data[i + 2] = newVal;
            }

            // 3. Basic thresholding
            const threshold = 128;
            for (let i = 0; i < data.length; i += 4) {
                const val = data[i] < threshold ? 0 : 255;
                data[i] = data[i + 1] = data[i + 2] = val;
            }

            // Put processed data back on canvas
            ctx.putImageData(imageData, 0, 0);

            // Return processed image
            resolve(canvas.toDataURL());
        };

        img.src = imageData;
    });
};
