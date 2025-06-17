import React, { useState } from 'react';
import './ReceiptScanner.css';

// Image preprocessing utilities will be imported here
// import { preprocessImage } from './imagePreprocessing';

const ReceiptScanner = () => {
    const [images, setImages] = useState([]);
    const [isProcessing, setIsProcessing] = useState(false);
    const [preprocessedImages, setPreprocessedImages] = useState([]);

    const handleImageUpload = (event) => {
        const files = Array.from(event.target.files);
        setImages(prevImages => [...prevImages, ...files]);

        // Preview images
        files.forEach(file => {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = new Image();
                img.src = e.target.result;
                img.onload = () => {
                    // Will add preprocessing here
                };
            };
            reader.readAsDataURL(file);
        });
    };

    const handleRemoveImage = (index) => {
        setImages(prevImages => prevImages.filter((_, i) => i !== index));
        setPreprocessedImages(prevImages => prevImages.filter((_, i) => i !== index));
    };

    return (
        <div className="receipt-scanner">
            <div className="receipt-scanner__header">
                <h2>Receipt Scanner</h2>
                <p className="receipt-scanner__subtitle">
                    Upload receipt images for automatic expense tracking
                </p>
            </div>

            <div className="receipt-scanner__upload">
                <label htmlFor="receipt-upload" className="upload-button">
                    Upload Receipts
                    <input
                        id="receipt-upload"
                        type="file"
                        accept="image/*"
                        multiple
                        onChange={handleImageUpload}
                        className="hidden"
                    />
                </label>
            </div>

            {images.length > 0 && (
                <div className="receipt-scanner__preview">
                    <h3>Uploaded Images ({images.length})</h3>
                    <div className="image-grid">
                        {images.map((image, index) => (
                            <div key={index} className="image-item">
                                <img
                                    src={URL.createObjectURL(image)}
                                    alt={`Receipt ${index + 1}`}
                                />
                                <button
                                    onClick={() => handleRemoveImage(index)}
                                    className="remove-button"
                                >
                                    ×
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {isProcessing && (
                <div className="processing-indicator">
                    <div className="loader"></div>
                    <p>Processing receipts...</p>
                </div>
            )}
        </div>
    );
};

export default ReceiptScanner;
