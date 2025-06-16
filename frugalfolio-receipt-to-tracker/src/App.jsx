// src/App.js

import React, { useState } from 'react';
import ReceiptScanner from './ReceiptScanner.jsx';
import './App.css';

function App() {
  // This state will hold the raw text extracted from the receipt
  const [rawText, setRawText] = useState('');

  return (
    <div className="App">
      <header className="App-header">
        <h1>Grocery Expense Automation</h1>
      </header>
      <main className="App-main">
        {/* Pass the setRawText function to the scanner component */}
        <ReceiptScanner setRawText={setRawText} />

        <div className="results-section">
          <h2>2. Raw Text Output</h2>
          <textarea
            value={rawText}
            readOnly
            placeholder="Scanned text will appear here..."
          />
        </div>
      </main>
    </div>
  );
}

export default App;