// src/ReceiptScanner.jsx (Final Version using Canvas API)

import React, { useState, useEffect } from 'react';
import Tesseract from 'tesseract.js';
import './ReceiptScanner.css';

// A new helper function to preprocess the image using the browser's Canvas API
const preprocessImage = (imageFile) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (event) => {
      const img = new Image();
      img.onload = () => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = img.width;
        canvas.height = img.height;
        ctx.drawImage(img, 0, 0);

        // Get pixel data
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const data = imageData.data;

        // Apply filters: Greyscale and Contrast
        for (let i = 0; i < data.length; i += 4) {
          // Greyscale: average of R, G, B
          const avg = (data[i] + data[i + 1] + data[i + 2]) / 3;
          data[i] = avg;     // Red
          data[i + 1] = avg; // Green
          data[i + 2] = avg; // Blue

          // A simple contrast adjustment
          const contrast = 1.5; // You can tune this value (e.g., 1.0 to 2.0)
          const adjusted = 128 + contrast * (avg - 128);
          const clamped = Math.max(0, Math.min(255, adjusted)); // Ensure value is between 0-255

          data[i] = clamped;
          data[i + 1] = clamped;
          data[i + 2] = clamped;
        }

        // Put the modified data back
        ctx.putImageData(imageData, 0, 0);

        // Get the result as a PNG blob
        canvas.toBlob(resolve, 'image/png');
      };
      img.onerror = reject;
      img.src = event.target.result;
    };
    reader.onerror = reject;
    reader.readAsDataURL(imageFile);
  });
};


const ReceiptScanner = ({ setRawText }) => {
  const [images, setImages] = useState([]);
  const [progress, setProgress] = useState(0);
  const [statusMessage, setStatusMessage] = useState('');
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    return () => {
      images.forEach(image => URL.revokeObjectURL(image.preview));
    };
  }, [images]);

  const handleFileChange = (e) => {
    if (e.target.files.length > 0) {
      images.forEach(image => URL.revokeObjectURL(image.preview));
      const fileArray = Array.from(e.target.files).map(file => ({
        file: file,
        preview: URL.createObjectURL(file)
      }));
      setImages(fileArray);
      setRawText('');
      setProgress(0);
      setStatusMessage('');
    }
  };

  const handleScan = async () => {
    if (images.length === 0) {
      alert('Please upload one or more images first.');
      return;
    }

    setIsLoading(true);
    setRawText('');
    setStatusMessage('Initializing OCR engine...');
    setProgress(0);
    
    let worker = null; 

    try {
      worker = await Tesseract.createWorker('eng');
      let fullText = '';

      for (let i = 0; i < images.length; i++) {
        setStatusMessage(`Processing image ${i + 1} of ${images.length}...`);
        
        // --- THIS IS THE NEW, RELIABLE PREPROCESSING STEP ---
        const processedImageBlob = await preprocessImage(images[i].file);
        // ---

        setStatusMessage(`Scanning image ${i + 1} of ${images.length}...`);
        setProgress(0);
        
        const recognizedText = await new Promise((resolve, reject) => {
          worker.worker.onmessage = (e) => {
            if (e.data.status === 'progress' && e.data.data?.status === 'recognizing text') {
              setProgress(Math.round(e.data.data.progress * 100));
            }
            if (e.data.status === 'resolve') {
              worker.worker.onmessage = null; 
              resolve(e.data.data.text);
            }
            if (e.data.status === 'error') {
              worker.worker.onmessage = null;
              reject(e.data.data);
            }
          };
          
          worker.recognize(processedImageBlob);
        });

        fullText += recognizedText + '\n\n---\n\n';
      }
      
      setRawText(fullText.trim());
      setStatusMessage('Scan complete!');
      setProgress(100);

    } catch (err) {
      console.error('An error occurred during the scan process:', err);
      setRawText('Error during OCR process. See console for details.');
      setStatusMessage('An error occurred.');
    } finally {
      if (worker) {
        await worker.terminate();
      }
      setIsLoading(false);
    }
  };

  return (
    <div className="receipt-scanner">
      {/* ... The JSX part remains completely unchanged ... */}
      <h2>1. Upload and Scan Receipt(s)</h2>
      <div className="uploader-ui">
        <input 
          type="file"
          multiple 
          onChange={handleFileChange} 
          accept="image/*"
        />
        <button onClick={handleScan} disabled={isLoading || images.length === 0}>
          {isLoading ? 'Scanning...' : `Scan ${images.length} Image(s)`}
        </button>
      </div>
      {isLoading && (
        <div className="progress-bar-container">
          <p>{statusMessage} ({progress}%)</p>
          <progress value={progress} max="100"></progress>
        </div>
      )}
      {images.length === 0 && !isLoading && <p>Select one or more receipt images.</p>}
      {images.length > 0 && (
        <div className="image-preview-grid">
          <h3>Image Previews (in order)</h3>
          <div className="previews-container">
            {images.map((image, index) => (
              <img key={index} src={image.preview} alt={`Receipt part ${index + 1}`} />
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

export default ReceiptScanner;