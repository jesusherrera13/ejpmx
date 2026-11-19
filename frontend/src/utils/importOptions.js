// Casillas de la carga por plantilla (ImportTemplateModal); Laravel las lee como '1'/'0' con $request->boolean()
export const appendImportOptions = (formData, options = {}) => {
  Object.entries(options).forEach(([key, value]) => formData.append(key, value ? '1' : '0'))
}
