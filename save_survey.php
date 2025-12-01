from flask import Flask, request, jsonify
from flask_cors import CORS
from sentence_transformers import SentenceTransformer
import joblib
import numpy as np
import pandas as pd
import os

app = Flask(__name__)
CORS(app)

# =========================
# LOAD MODEL + ENCODER
# =========================
model = joblib.load("career_model.pkl")              # your ML model
label_encoder = joblib.load("label_encoder.pkl")     # encoder for labels
sentence_model = SentenceTransformer("all-MiniLM-L6-v2")  # text encoder

# =========================
# ROOT ENDPOINT
# =========================
@app.route("/", methods=["GET"])
def home():
    return jsonify({"message": "Career Prediction API is running!"})

# =========================
# MAIN PREDICTION ENDPOINT
# =========================
@app.route("/predict", methods=["POST"])
def predict():
    try:
        if not request.is_json:
            return jsonify({"error": "Request must be JSON"}), 400

        data = request.get_json()

        # Validate input
        if "answers" not in data:
            return jsonify({"error": "Missing 'answers' field"}), 400

        user_text = " ".join(data["answers"])  # join survey answers

        # Encode using SentenceTransformer
        embedding = sentence_model.encode([user_text])
        prediction = model.predict(embedding)[0]
        confidence = model.predict_proba(embedding)[0].max()

        decoded_prediction = label_encoder.inverse_transform([prediction])[0]

        return jsonify({
            "recommended_career": decoded_prediction,
            "confidence_score": float(confidence)
        })

    except Exception as e:
        return jsonify({"error": str(e)}), 500


# =========================
# RUN SERVER (Render needs this)
# =========================
if __name__ == "__main__":
    port = int(os.environ.get("PORT", 10000))  # Render will inject PORT
    app.run(host="0.0.0.0", port=port)

