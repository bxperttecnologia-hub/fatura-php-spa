<div class="modal fade" id="modalEmployee" tabindex="-1" aria-labelledby="modalEmployeeLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formEmployee" enctype="multipart/form-data">

                <div class="modal-header border-0">
                    <h5 class="modal-title">Cadastrar Funcionário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <!-- STEPS -->
                    <div class="steps mb-4">
                        <div class="step active">1</div>
                        <div class="step">2</div>
                        <div class="step">3</div>
                    </div>

                    <!-- STEP 1 -->
                    <div class="step-content active">
                        <div class="card p-3 border-0 shadow-sm">
                            <h6 class="mb-3">Dados Pessoais</h6>

                            <div class="row">
                                <div class="col-md-6">
                                    <label>Nome Completo</label>
                                    <input type="text" name="name" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label>Data de Nascimento</label>
                                    <input type="date" name="birth_date" class="form-control">
                                </div>

                                <div class="col-md-6 mt-3">
                                    <label>Tipo de documento</label>
                                    <select name="document_type" class="form-select">
                                        <option>BI</option>
                                        <option>Passaporte</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mt-3">
                                    <label>Nº do BI/Passaporte</label>
                                    <input type="text" name="bi" class="form-control">
                                </div>

                                <div class="col-md-6 mt-3">
                                    <label>Estado civil</label>
                                    <select name="marital_status" class="form-select">
                                        <option>Selecione...</option>
                                        <option>Solteiro(a)</option>
                                        <option>Casado(a)</option>
                                        <option>Divorciado(a)</option>
                                    </select>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- STEP 2 -->
                    <div class="step-content">
                        <div class="card p-3 border-0 shadow-sm">
                            <h6 class="mb-3">Dados Profissionais</h6>

                            <div class="row">
                                <div class="col-md-6">
                                    <label>Cargo</label>
                                    <select name="position" id="positionSelect" class="form-select"></select>
                                </div>

                                <div class="col-md-6">
                                    <label>Salário</label>
                                    <input type="number" name="salary" class="form-control">
                                </div>

                                <div class="col-md-6 mt-3">
                                    <label>Status</label>
                                    <select name="status" class="form-select">
                                        <option>Ativo</option>
                                        <option>Inativo</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mt-3">
                                    <label>Tipo de vínculo</label>
                                    <select type="text" name="contract_type" class="form-select">
                                        <option selected>Selecione o tipo</option>
                                        <option value="efetivo">Efetivo</option>
                                        <option value="atermo">A Termo</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mt-3">
                                    <label>Data de Admissão</label>
                                    <input type="date" name="admission_date" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 3 -->
                    <div class="step-content">
                        <div class="card p-3 border-0 shadow-sm">
                            <h6 class="mb-3">Outros & Uploads</h6>

                            <div class="row">
                                <div class="col-md-6">
                                    <label>IBAN</label>
                                    <input type="text" name="iban" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label>Nível acadêmico</label>
                                    <select name="academic_level" class="form-control">
                                        <option>Selecione...</option>
                                        <option>Ensino médio</option>
                                        <option>Superior</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mt-3">
                                    <label>Foto</label>
                                    <input type="file" name="photo" class="form-control">
                                </div>

                                <div class="col-md-6 mt-3">
                                    <label>Documento 1</label>
                                    <input type="file" name="doc1" class="form-control">
                                </div>

                                <div class="col-md-6 mt-3">
                                    <label>Documento 2</label>
                                    <input type="file" name="doc2" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-light" id="prevBtn">Voltar</button>
                    <button type="button" class="btn btn-primary" id="nextBtn">Próximo</button>
                    <button type="submit" class="btn btn-success d-none" id="submitBtn">Salvar</button>
                </div>

            </form>
        </div>
    </div>
</div>