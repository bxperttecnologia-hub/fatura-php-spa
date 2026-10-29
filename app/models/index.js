import Sequelize, { DataTypes } from 'sequelize';
import { AgtLog } from './AgtLog.js';
import { AlertLog } from './AlertLog.js';
import { AlertRule } from './AlertRule.js';
import { Attendance } from './Attendance.js';
import { AuditLog } from './AuditLog.js';
import { BusinessHealthScore } from './BusinessHealthScore.js';
import { BusinessInsight } from './BusinessInsight.js';
import { CollectionSchedule } from './CollectionSchedule.js';
import { Company } from './Company.js';
import { CompanyHasUser } from './CompanyHasUser.js';
import { Contact } from './Contact.js';
import { CopilotAction } from './CopilotAction.js';
import { CopilotConversation } from './CopilotConversation.js';
import { CopilotMessage } from './CopilotMessage.js';
import { Country } from './Country.js';
import { CreditNote } from './CreditNote.js';
import { CreditNoteItem } from './CreditNoteItem.js';
import { Currency } from './Currency.js';
import { CustomerScore } from './CustomerScore.js';
import { DebitNote } from './DebitNote.js';
import { DebitNoteItem } from './DebitNoteItem.js';
import { DeliveryNote } from './DeliveryNote.js';
import { DeliveryNoteItem } from './DeliveryNoteItem.js';
import { Department } from './Department.js';
import { DocumentSequence } from './DocumentSequence.js';
import { Employee } from './Employee.js';
import { Evaluation } from './Evaluation.js';
import { EvaluationAnswer } from './EvaluationAnswer.js';
import { EvaluationCriteria } from './EvaluationCriteria.js';
import { EvaluationCycle } from './EvaluationCycle.js';
import { EvaluationTemplate } from './EvaluationTemplate.js';
import { Guide } from './Guide.js';
import { GuideItem } from './GuideItem.js';
import { Holiday } from './Holiday.js';
import { Instruction } from './Instruction.js';
import { Invoice } from './Invoice.js';
import { InvoiceItem } from './InvoiceItem.js';
import { InvoiceStatu } from './InvoiceStatu.js';
import { Item } from './Item.js';
import { MonthlyReport } from './MonthlyReport.js';
import { Notification } from './Notification.js';
import { PasswordReset } from './PasswordReset.js';
import { Payment } from './Payment.js';
import { PaymentMethodsContact } from './PaymentMethodsContact.js';
import { Payroll } from './Payroll.js';
import { PhoneVerification } from './PhoneVerification.js';
import { Plan } from './Plan.js';
import { Position } from './Position.js';
import { PresetItem } from './PresetItem.js';
import { Product } from './Product.js';
import { Proforma } from './Proforma.js';
import { ProformaItem } from './ProformaItem.js';
import { ProformaSequence } from './ProformaSequence.js';
import { ProformaStatu } from './ProformaStatu.js';
import { Receipt } from './Receipt.js';
import { Report } from './Report.js';
import { RhSetting } from './RhSetting.js';
import { Session } from './Session.js';
import { Stock } from './Stock.js';
import { StockItem } from './StockItem.js';
import { StockMovement } from './StockMovement.js';
import { Subscription } from './Subscription.js';
import { SubscriptionOrder } from './SubscriptionOrder.js';
import { SupportAgent } from './SupportAgent.js';
import { User } from './User.js';
import { Vacation } from './Vacation.js';

export {
  AgtLog,
  AlertLog,
  AlertRule,
  Attendance,
  AuditLog,
  BusinessHealthScore,
  BusinessInsight,
  CollectionSchedule,
  Company,
  CompanyHasUser,
  Contact,
  CopilotAction,
  CopilotConversation,
  CopilotMessage,
  Country,
  CreditNote,
  CreditNoteItem,
  Currency,
  CustomerScore,
  DebitNote,
  DebitNoteItem,
  DeliveryNote,
  DeliveryNoteItem,
  Department,
  DocumentSequence,
  Employee,
  Evaluation,
  EvaluationAnswer,
  EvaluationCriteria,
  EvaluationCycle,
  EvaluationTemplate,
  Guide,
  GuideItem,
  Holiday,
  Instruction,
  Invoice,
  InvoiceItem,
  InvoiceStatu,
  Item,
  MonthlyReport,
  Notification,
  PasswordReset,
  Payment,
  PaymentMethodsContact,
  Payroll,
  PhoneVerification,
  Plan,
  Position,
  PresetItem,
  Product,
  Proforma,
  ProformaItem,
  ProformaSequence,
  ProformaStatu,
  Receipt,
  Report,
  RhSetting,
  Session,
  Stock,
  StockItem,
  StockMovement,
  Subscription,
  SubscriptionOrder,
  SupportAgent,
  User,
  Vacation,
};

const models = {
  AgtLog,
  AlertLog,
  AlertRule,
  Attendance,
  AuditLog,
  BusinessHealthScore,
  BusinessInsight,
  CollectionSchedule,
  Company,
  CompanyHasUser,
  Contact,
  CopilotAction,
  CopilotConversation,
  CopilotMessage,
  Country,
  CreditNote,
  CreditNoteItem,
  Currency,
  CustomerScore,
  DebitNote,
  DebitNoteItem,
  DeliveryNote,
  DeliveryNoteItem,
  Department,
  DocumentSequence,
  Employee,
  Evaluation,
  EvaluationAnswer,
  EvaluationCriteria,
  EvaluationCycle,
  EvaluationTemplate,
  Guide,
  GuideItem,
  Holiday,
  Instruction,
  Invoice,
  InvoiceItem,
  InvoiceStatu,
  Item,
  MonthlyReport,
  Notification,
  PasswordReset,
  Payment,
  PaymentMethodsContact,
  Payroll,
  PhoneVerification,
  Plan,
  Position,
  PresetItem,
  Product,
  Proforma,
  ProformaItem,
  ProformaSequence,
  ProformaStatu,
  Receipt,
  Report,
  RhSetting,
  Session,
  Stock,
  StockItem,
  StockMovement,
  Subscription,
  SubscriptionOrder,
  SupportAgent,
  User,
  Vacation,
};

export default models;
