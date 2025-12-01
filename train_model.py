# Step 1: Setup
import pandas as pd
import numpy as np
from sklearn.model_selection import train_test_split, GridSearchCV
from sklearn.preprocessing import LabelEncoder, MultiLabelBinarizer
from sklearn.ensemble import GradientBoostingClassifier
from sklearn.metrics import accuracy_score, classification_report
from sklearn.calibration import CalibratedClassifierCV
from imblearn.over_sampling import SMOTE
import joblib

# Step 2: Load your dataset (Excel/CSV)
# Replace with your file path in Google Drive
df = pd.read_excel("/content/drive/MyDrive/VocAItion/training_dataset.xlsx")

# Step 3: Preprocess survey questions (q1..q42)
label_encoders = {}
question_cols = [c for c in df.columns if c.startswith("q")]  # only q1..q42
for col in question_cols:
    le = LabelEncoder()
    df[col] = le.fit_transform(df[col].astype(str))
    label_encoders[col] = le

# Step 3.1: Encode RIASEC code column
df["code_list"] = df["code"].apply(lambda x: x.split(","))  # split "C,R,S" into list
mlb = MultiLabelBinarizer()
riasec_encoded = pd.DataFrame(
    mlb.fit_transform(df["code_list"]),
    columns=mlb.classes_,
    index=df.index
)

# Merge encoded RIASEC features back into dataframe
df = pd.concat([df, riasec_encoded], axis=1)

# Step 3.2: Encode target column (courses)
target_column = "courses"
target_encoder = LabelEncoder()
df[target_column] = target_encoder.fit_transform(df[target_column])

# Final feature set: drop original 'courses', 'code', and 'code_list'
X = df.drop([target_column, "code", "code_list"], axis=1)
y = df[target_column]

# Step 3.5: Balance dataset with SMOTE
smote = SMOTE(random_state=42)
X_resampled, y_resampled = smote.fit_resample(X, y)

# Step 4: Train/test split (use resampled data)
X_train, X_test, y_train, y_test = train_test_split(
    X_resampled, y_resampled, test_size=0.2, random_state=42, stratify=y_resampled
)

# Step 5: Model training with hyperparameter tuning
model = GradientBoostingClassifier()
param_grid = {
    'n_estimators': [100, 200],
    'learning_rate': [0.05, 0.1],
    'max_depth': [3, 5]
}
grid = GridSearchCV(model, param_grid, cv=5, scoring='accuracy')
grid.fit(X_train, y_train)

best_model = grid.best_estimator_

# Step 6: Confidence calibration
calibrated_model = CalibratedClassifierCV(best_model, method='isotonic', cv=5)
calibrated_model.fit(X_train, y_train)

# Step 7: Evaluate
y_pred = calibrated_model.predict(X_test)
y_prob = calibrated_model.predict_proba(X_test)

print("Accuracy:", accuracy_score(y_test, y_pred))
print(classification_report(y_test, y_pred, target_names=target_encoder.classes_))

# Step 8: Save artifacts
joblib.dump(calibrated_model, "/content/drive/MyDrive/VocAItion/best_model.pkl")
joblib.dump(label_encoders, "/content/drive/MyDrive/VocAItion/feature_encoders.pkl")
joblib.dump(target_encoder, "/content/drive/MyDrive/VocAItion/target_encoder.pkl")
joblib.dump(mlb, "/content/drive/MyDrive/VocAItion/riasec_encoder.pkl")  # NEW: save RIASEC encoder