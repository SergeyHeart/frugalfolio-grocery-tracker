// In src/main.jsx

import React from 'react'
import ReactDOM from 'react-dom/client'
// Before
// import App from './App.js' 
// After
import App from './App.jsx'
import './index.css'

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
